import 'package:flutter/material.dart';

import 'core/theme/app_theme.dart';

/// Raíz de la aplicación.
///
/// En la fase 2 el `home` se sustituye por `MaterialApp.router` con go_router
/// y el guard de sesión.
class CampusConnectApp extends StatelessWidget {
  const CampusConnectApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Campus Connect',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.claro,
      darkTheme: AppTheme.oscuro,
      themeMode: ThemeMode.system,
      home: const _PantallaInicial(),
    );
  }
}

class _PantallaInicial extends StatelessWidget {
  const _PantallaInicial();

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;

    return Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.school_outlined, size: 64, color: esquema.primary),
            const SizedBox(height: 16),
            Text(
              'Campus Connect',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
          ],
        ),
      ),
    );
  }
}
