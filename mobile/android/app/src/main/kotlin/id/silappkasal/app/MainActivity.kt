package id.silappkasal.app

import android.app.Activity
import android.content.ClipData
import android.content.Intent
import android.net.Uri
import android.provider.MediaStore
import androidx.core.content.FileProvider
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import java.io.File
import java.util.Locale
import java.util.UUID

class MainActivity : FlutterActivity() {
    companion object {
        private const val CHANNEL_NAME = "id.silappkasal.app/native"
        private const val REQUEST_PICK_FILES = 4101
        private const val REQUEST_TAKE_PHOTO = 4102
        private const val REQUEST_SAVE_DOCUMENT = 4103
        private const val MAX_DOCUMENT_BYTES = 25 * 1024 * 1024
        private val ALLOWED_DOCUMENT_TYPES = setOf(
            "application/pdf",
            "image/jpeg",
            "image/png",
            "image/webp",
        )
    }

    private var pendingPickerResult: MethodChannel.Result? = null
    private var pendingSave: PendingSave? = null
    private var pendingCameraUri: Uri? = null
    private var pendingCameraFile: File? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, CHANNEL_NAME)
            .setMethodCallHandler(::handleMethodCall)
    }

    private fun handleMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "pickFiles" -> pickFiles(call, result)
            "previewDocument" -> previewDocument(call, result)
            "saveDocument" -> saveDocument(call, result)
            else -> result.notImplemented()
        }
    }

    private fun pickFiles(call: MethodCall, result: MethodChannel.Result) {
        if (pendingPickerResult != null || pendingSave != null) {
            result.error("BUSY", "Another Android document action is active.", null)
            return
        }

        val source = call.argument<String>("source") ?: "files"
        val allowMultiple = call.argument<Boolean>("allowMultiple") == true
        val mimeTypes = call.argument<List<String>>("mimeTypes")
            .orEmpty()
            .filter(::isValidMimeType)
            .distinct()

        pendingPickerResult = result
        try {
            when (source) {
                "camera" -> launchCamera()
                "gallery" -> launchGallery(allowMultiple)
                else -> launchFilePicker(mimeTypes, allowMultiple)
            }
        } catch (error: Exception) {
            clearPendingPicker()
            result.error("PICKER_UNAVAILABLE", "File source is unavailable.", null)
        }
    }

    private fun launchCamera() {
        val directory = File(cacheDir, "mobile_uploads").apply { mkdirs() }
        val file = File(directory, "camera-${UUID.randomUUID()}.jpg")
        val uri = FileProvider.getUriForFile(this, "$packageName.files", file)
        pendingCameraFile = file
        pendingCameraUri = uri

        val intent = Intent(MediaStore.ACTION_IMAGE_CAPTURE).apply {
            putExtra(MediaStore.EXTRA_OUTPUT, uri)
            clipData = ClipData.newRawUri("SILAPPKASAL photo", uri)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_WRITE_URI_PERMISSION)
        }
        startActivityForResult(intent, REQUEST_TAKE_PHOTO)
    }

    private fun launchGallery(allowMultiple: Boolean) {
        val intent = Intent(Intent.ACTION_GET_CONTENT).apply {
            addCategory(Intent.CATEGORY_OPENABLE)
            type = "image/*"
            putExtra(Intent.EXTRA_ALLOW_MULTIPLE, allowMultiple)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        startActivityForResult(Intent.createChooser(intent, "Pilih dari galeri"), REQUEST_PICK_FILES)
    }

    private fun launchFilePicker(mimeTypes: List<String>, allowMultiple: Boolean) {
        val intent = Intent(Intent.ACTION_OPEN_DOCUMENT).apply {
            addCategory(Intent.CATEGORY_OPENABLE)
            type = when {
                mimeTypes.isEmpty() -> "*/*"
                mimeTypes.size == 1 -> mimeTypes.single()
                mimeTypes.all { it.startsWith("image/") } -> "image/*"
                else -> "*/*"
            }
            if (mimeTypes.size > 1) {
                putExtra(Intent.EXTRA_MIME_TYPES, mimeTypes.toTypedArray())
            }
            putExtra(Intent.EXTRA_ALLOW_MULTIPLE, allowMultiple)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION)
        }
        startActivityForResult(intent, REQUEST_PICK_FILES)
    }

    private fun previewDocument(call: MethodCall, result: MethodChannel.Result) {
        val document = validatedDocument(call, result) ?: return
        try {
            val directory = File(cacheDir, "mobile_documents").apply { mkdirs() }
            val file = File(directory, document.filename).apply { writeBytes(document.bytes) }
            val uri = FileProvider.getUriForFile(this, "$packageName.files", file)
            val viewIntent = Intent(Intent.ACTION_VIEW).apply {
                setDataAndType(uri, document.mimeType)
                clipData = ClipData.newRawUri("SILAPPKASAL document", uri)
                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            }
            startActivity(Intent.createChooser(viewIntent, "Pratinjau dokumen"))
            result.success(true)
        } catch (error: Exception) {
            result.error("PREVIEW_UNAVAILABLE", "No application can preview this document.", null)
        }
    }

    private fun saveDocument(call: MethodCall, result: MethodChannel.Result) {
        if (pendingPickerResult != null || pendingSave != null) {
            result.error("BUSY", "Another Android document action is active.", null)
            return
        }
        val document = validatedDocument(call, result) ?: return
        pendingSave = PendingSave(document, result)
        try {
            val intent = Intent(Intent.ACTION_CREATE_DOCUMENT).apply {
                addCategory(Intent.CATEGORY_OPENABLE)
                type = document.mimeType
                putExtra(Intent.EXTRA_TITLE, document.filename)
            }
            startActivityForResult(intent, REQUEST_SAVE_DOCUMENT)
        } catch (error: Exception) {
            pendingSave = null
            result.error("SAVE_UNAVAILABLE", "Document storage is unavailable.", null)
        }
    }

    private fun validatedDocument(
        call: MethodCall,
        result: MethodChannel.Result,
    ): NativeDocument? {
        val bytes = call.argument<ByteArray>("bytes")
        val rawFilename = call.argument<String>("filename")
        val mimeType = call.argument<String>("mimeType")
            ?.lowercase(Locale.ROOT)
            ?.substringBefore(';')
            ?.trim()
        if (bytes == null || bytes.isEmpty() || bytes.size > MAX_DOCUMENT_BYTES ||
            rawFilename == null || mimeType == null || mimeType !in ALLOWED_DOCUMENT_TYPES
        ) {
            result.error("INVALID_DOCUMENT", "Document metadata is invalid.", null)
            return null
        }
        return NativeDocument(bytes, safeFilename(rawFilename, mimeType), mimeType)
    }

    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        when (requestCode) {
            REQUEST_PICK_FILES -> finishFilePicker(resultCode, data)
            REQUEST_TAKE_PHOTO -> finishCamera(resultCode)
            REQUEST_SAVE_DOCUMENT -> finishDocumentSave(resultCode, data)
        }
    }

    private fun finishFilePicker(resultCode: Int, data: Intent?) {
        val result = pendingPickerResult ?: return
        pendingPickerResult = null
        if (resultCode != Activity.RESULT_OK) {
            result.success(emptyList<String>())
            return
        }

        val uris = mutableListOf<Uri>()
        data?.clipData?.let { clip ->
            for (index in 0 until clip.itemCount) uris += clip.getItemAt(index).uri
        } ?: data?.data?.let(uris::add)

        val permissionFlags = data?.flags?.and(
            Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_WRITE_URI_PERMISSION,
        ) ?: 0
        if (data?.action == Intent.ACTION_OPEN_DOCUMENT && permissionFlags != 0) {
            uris.forEach { uri ->
                try {
                    contentResolver.takePersistableUriPermission(uri, permissionFlags)
                } catch (_: SecurityException) {
                    // The immediate read grant is sufficient when a provider is not persistable.
                }
            }
        }
        result.success(uris.distinct().map(Uri::toString))
    }

    private fun finishCamera(resultCode: Int) {
        val result = pendingPickerResult ?: return
        val uri = pendingCameraUri
        val file = pendingCameraFile
        clearPendingPicker()
        if (resultCode == Activity.RESULT_OK && uri != null && file?.length()?.let { it > 0 } == true) {
            result.success(listOf(uri.toString()))
        } else {
            file?.delete()
            result.success(emptyList<String>())
        }
    }

    private fun finishDocumentSave(resultCode: Int, data: Intent?) {
        val pending = pendingSave ?: return
        pendingSave = null
        val uri = data?.data
        if (resultCode != Activity.RESULT_OK || uri == null) {
            pending.result.success(false)
            return
        }
        try {
            contentResolver.openOutputStream(uri, "w")?.use { output ->
                output.write(pending.document.bytes)
            } ?: error("Output stream unavailable")
            pending.result.success(true)
        } catch (error: Exception) {
            pending.result.error("SAVE_FAILED", "Document could not be written.", null)
        }
    }

    private fun clearPendingPicker() {
        pendingPickerResult = null
        pendingCameraUri = null
        pendingCameraFile = null
    }

    private fun isValidMimeType(value: String): Boolean =
        value.matches(Regex("^[a-zA-Z0-9][a-zA-Z0-9!#$&^_.+-]*/[a-zA-Z0-9][a-zA-Z0-9!#$&^_.+*-]*$"))

    private fun safeFilename(value: String, mimeType: String): String {
        val cleaned = value
            .replace(Regex("[\\x00-\\x1F\\x7F\\\\/:*?\"<>|]"), "-")
            .trim()
            .take(120)
            .ifEmpty { "dokumen-SILAPPKASAL" }
        if (cleaned.contains('.')) return cleaned
        return cleaned + when (mimeType) {
            "application/pdf" -> ".pdf"
            "image/jpeg" -> ".jpg"
            "image/png" -> ".png"
            "image/webp" -> ".webp"
            else -> ".bin"
        }
    }

    private data class NativeDocument(
        val bytes: ByteArray,
        val filename: String,
        val mimeType: String,
    )

    private data class PendingSave(
        val document: NativeDocument,
        val result: MethodChannel.Result,
    )
}
