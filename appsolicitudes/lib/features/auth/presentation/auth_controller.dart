import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../data/auth_repository.dart';
import '../domain/usuario.dart';

/// Estado de la sesión. El router decide a dónde llevar al usuario a partir de
/// estos tres casos.
@immutable
sealed class EstadoSesion {
  const EstadoSesion();
}

/// Todavía no se sabe si hay sesión: se está consultando `GET /auth/me`.
class SesionCargando extends EstadoSesion {
  const SesionCargando();
}

class SesionActiva extends EstadoSesion {
  const SesionActiva(this.usuario);
  final Usuario usuario;
}

class SinSesion extends EstadoSesion {
  const SinSesion();
}

/// Gobierna el ciclo de vida de la sesión.
class AuthController extends Notifier<EstadoSesion> {
  @override
  EstadoSesion build() {
    final api = ref.read(apiClientProvider);
    // Si cualquier petición recibe un 401, la sesión cae aquí también.
    api.escucharExpiracionDeSesion(() {
      if (state is! SinSesion) state = const SinSesion();
    });

    // No se puede await en build: se lanza y el estado inicial es "cargando".
    Future.microtask(restaurarSesion);
    return const SesionCargando();
  }

  AuthRepository get _repositorio => ref.read(authRepositoryProvider);

  /// Al abrir la aplicación: si hay token guardado, se valida contra el
  /// servidor antes de dar la sesión por buena.
  Future<void> restaurarSesion() async {
    final token = await ref.read(tokenStorageProvider).leer();
    if (token == null) {
      state = const SinSesion();
      return;
    }

    try {
      state = SesionActiva(await _repositorio.consultarPerfil());
    } on ApiException {
      // Token vencido o revocado; el interceptor ya lo borró si fue un 401.
      state = const SinSesion();
    } on FormatException {
      state = const SinSesion();
    }
  }

  /// Devuelve `null` si el inicio de sesión salió bien, o la excepción para que
  /// la pantalla muestre el mensaje y los errores por campo.
  Future<ApiException?> iniciarSesion({
    required String email,
    required String password,
  }) async {
    try {
      final usuario = await _repositorio.iniciarSesion(
        email: email.trim(),
        password: password,
      );
      state = SesionActiva(usuario);
      return null;
    } on ApiException catch (error) {
      return error;
    } on FormatException catch (error) {
      // El backend respondió algo con una forma que no esperábamos.
      return ApiException(mensaje: error.message);
    }
  }

  Future<void> cerrarSesion() async {
    // El estado cambia de inmediato: el usuario no debe esperar a la red para
    // salir de la aplicación.
    state = const SinSesion();
    try {
      await _repositorio.cerrarSesion();
    } on ApiException {
      // El token local ya se borró en el repositorio.
    }
  }
}

final authControllerProvider =
    NotifierProvider<AuthController, EstadoSesion>(AuthController.new);

/// Usuario autenticado, o `null` si no hay sesión. Atajo para la interfaz.
final usuarioActualProvider = Provider<Usuario?>((ref) {
  final estado = ref.watch(authControllerProvider);
  return estado is SesionActiva ? estado.usuario : null;
});
