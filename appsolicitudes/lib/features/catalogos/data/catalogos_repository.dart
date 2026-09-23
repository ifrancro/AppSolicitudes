import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/tipo_solicitud.dart';

/// Lectura de los catálogos que necesita la aplicación móvil.
class CatalogosRepository {
  const CatalogosRepository(this._api);

  final ApiClient _api;

  Future<List<TipoSolicitud>> consultarTiposDeSolicitud() async {
    final respuesta = await _api.get('/tipos-solicitud');
    return _comoLista(respuesta)
        .map((elemento) =>
            TipoSolicitud.desdeJson(elemento as Map<String, dynamic>))
        .toList();
  }

  /// Acepta una lista en la raíz o envuelta en `data`, que es como Laravel
  /// serializa una colección con API Resources.
  static List<dynamic> _comoLista(dynamic respuesta) {
    if (respuesta is List) return respuesta;
    if (respuesta is Map && respuesta['data'] is List) {
      return respuesta['data'] as List<dynamic>;
    }
    throw const FormatException('Se esperaba una lista del catálogo.');
  }
}

final catalogosRepositoryProvider = Provider<CatalogosRepository>((ref) {
  return CatalogosRepository(ref.watch(apiClientProvider));
});

/// Tipos de solicitud, cacheados mientras la aplicación siga abierta.
///
/// El catálogo casi no cambia, así que no tiene sentido volver a pedirlo cada
/// vez que se abre el formulario de nueva solicitud.
final tiposDeSolicitudProvider = FutureProvider<List<TipoSolicitud>>((ref) {
  return ref.watch(catalogosRepositoryProvider).consultarTiposDeSolicitud();
});
