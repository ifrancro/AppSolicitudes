import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/estado_mensaje.dart';
import '../../catalogos/data/catalogos_repository.dart';

/// Listado de las solicitudes del estudiante.
///
/// La fase 4 sustituye el cuerpo por la lista real de `GET /mis-solicitudes`.
/// Por ahora comprueba que el catálogo de tipos llega desde la API.
class MisSolicitudesScreen extends ConsumerWidget {
  const MisSolicitudesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tipos = ref.watch(tiposDeSolicitudProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Mis solicitudes')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(
            const SnackBar(
              content: Text('El formulario llega en la siguiente entrega.'),
            ),
          ),
        icon: const Icon(Icons.add),
        label: const Text('Nueva'),
      ),
      body: tipos.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => EstadoError(
          mensaje: error.toString(),
          alReintentar: () => ref.invalidate(tiposDeSolicitudProvider),
        ),
        data: (lista) => EstadoMensaje.vacio(
          icono: Icons.assignment_outlined,
          titulo: 'Todavía no tienes solicitudes',
          detalle: lista.isEmpty
              ? 'El catálogo de tipos aún no tiene registros.'
              : 'Ya puedes registrar una: hay ${lista.length} tipos '
                  'disponibles.',
        ),
      ),
    );
  }
}
