# BACKLOG

## Localization

### Minor UI Localization

Status: Open
Priority: Low

Affected Component:
- QueryErrorState

Current Text:
- Data could not be loaded
- Retry

Expected Indonesian:
- Data tidak dapat dimuat
- Coba lagi

Notes:
- Does not block functionality.
- Does not block M25 completion.
- Shared component localization can be addressed in a future localization polish phase.

## M26-C Follow-up

Add missing policy edge-case tests:
- Admin forbidden pending list
- Self approval blocked
- Single SA self approval allowed

## Production Readiness

### Adaptive Rate Limiting

Status: Backlog (Pre Go-Live)
Priority: High

Reason:
Selama development dan UAT digunakan rate limit yang lebih longgar agar tidak mengganggu proses pengujian. Sebelum production, seluruh endpoint publik harus menggunakan kebijakan rate limiting yang disesuaikan berdasarkan jenis endpoint.

Target Design:

| Endpoint | Development/UAT | Production (Rekomendasi) | Alasan |
|---|---:|---:|---|
| Login | 20/min | 5/min | Melindungi dari brute force |
| Register submit | 20/min | 5-10/min | Mencegah spam pendaftaran |
| Universities | 20/min | 120/min | Data lookup, aman jika lebih longgar |
| Faculties | 20/min | 120/min | Dipanggil berkali-kali oleh cascading dropdown |
| Study Programs | 20/min | 120/min | Sama seperti faculties |
| Tracking | 20/min | 20/min | Cukup ketat untuk mencegah enumeration |

Configuration:
Rate limit tidak boleh hardcoded.

Harus menggunakan konfigurasi yang mudah diubah, misalnya:

```env
RATE_LIMIT_LOGIN=5
RATE_LIMIT_REGISTER=10
RATE_LIMIT_PUBLIC_LOOKUP=120
RATE_LIMIT_TRACKING=20
```

Atau melalui file konfigurasi Laravel seperti `config/rate_limit.php`, sehingga perubahan tidak memerlukan edit source code.

Future Enhancements (Go-Live):
- Rate limit berdasarkan kombinasi IP + email untuk login.
- Progressive backoff: semakin banyak gagal login, semakin lama jeda.
- Audit log untuk request yang melebihi batas.
- Integrasi dengan reverse proxy/CDN seperti Nginx atau Cloudflare bila nanti digunakan.
- Friendly UI ketika menerima HTTP 429:
  - Menampilkan countdown sisa waktu.
  - Menjelaskan bahwa percobaan terlalu banyak.
  - Tidak hanya menampilkan pesan error mentah.
