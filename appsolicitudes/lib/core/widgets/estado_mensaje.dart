import 'package:flutter/material.dart';

/// Mensaje centrado con icono, usado para los estados vacío y de error.
///
/// Centralizarlo evita que cada pantalla invente su propia versión y mantiene
/// el mismo espaciado en toda la aplicación.
class EstadoMensaje extends StatelessWidget {
  const EstadoMensaje({
    super.key,
    required this.icono,
    required this.titulo,
    this.detalle,
    this.textoAccion,
    this.alPulsarAccion,
    this.colorIcono,
  });

  /// Estado vacío: no hay datos todavía, y no es un fallo.
  const EstadoMensaje.vacio({
    Key? key,
    required IconData icono,
    required String titulo,
    String? detalle,
    String? textoAccion,
    VoidCallback? alPulsarAccion,
  }) : this(
          key: key,
          icono: icono,
          titulo: titulo,
          detalle: detalle,
          textoAccion: textoAccion,
          alPulsarAccion: alPulsarAccion,
        );

  final IconData icono;
  final String titulo;
  final String? detalle;
  final String? textoAccion;
  final VoidCallback? alPulsarAccion;
  final Color? colorIcono;

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    final textos = Theme.of(context).textTheme;

    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icono,
              size: 56,
              color: colorIcono ?? esquema.onSurfaceVariant,
            ),
            const SizedBox(height: 16),
            Text(
              titulo,
              textAlign: TextAlign.center,
              style: textos.titleMedium,
            ),
            if (detalle != null) ...[
              const SizedBox(height: 8),
              Text(
                detalle!,
                textAlign: TextAlign.center,
                style: textos.bodyMedium?.copyWith(
                  color: esquema.onSurfaceVariant,
                ),
              ),
            ],
            if (textoAccion != null && alPulsarAccion != null) ...[
              const SizedBox(height: 24),
              FilledButton.tonal(
                onPressed: alPulsarAccion,
                child: Text(textoAccion!),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Estado de error con la causa y la opción de reintentar.
class EstadoError extends StatelessWidget {
  const EstadoError({
    super.key,
    required this.mensaje,
    this.alReintentar,
  });

  final String mensaje;
  final VoidCallback? alReintentar;

  @override
  Widget build(BuildContext context) {
    return EstadoMensaje(
      icono: Icons.cloud_off_outlined,
      titulo: 'No se pudo cargar la información',
      detalle: mensaje,
      colorIcono: Theme.of(context).colorScheme.error,
      textoAccion: alReintentar == null ? null : 'Reintentar',
      alPulsarAccion: alReintentar,
    );
  }
}
