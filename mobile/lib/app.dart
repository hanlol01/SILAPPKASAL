import 'package:flutter/material.dart';

import 'features/web_portal/reporter_webview_page.dart';

class SilappkasalApp extends StatelessWidget {
  const SilappkasalApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SILAPPKASAL',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF2BC3C8),
          brightness: Brightness.dark,
        ),
        useMaterial3: true,
      ),
      home: const ReporterWebViewPage(),
    );
  }
}
