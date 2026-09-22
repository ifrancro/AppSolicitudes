import 'package:flutter/material.dart';

import '../../../core/widgets/estado_mensaje.dart';

/// Bandeja de notificaciones del estudiante.
///
/// La fase 7 conecta `GET /notificaciones` y el marcado como leída.
class NotificacionesScreen extends StatelessWidget {
  const NotificacionesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Notificaciones')),
      body: const EstadoMensaje.vacio(
        icono: Icons.notifications_none,
        titulo: 'No tienes notificaciones',
        detalle: 'Aquí verás los avisos cuando cambie el estado de tus '
            'solicitudes.',
      ),
    );
  }
}
