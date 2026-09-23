import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/perfil_screen.dart';
import '../../features/notificaciones/presentation/notificaciones_screen.dart';
import '../../features/solicitudes/presentation/mis_solicitudes_screen.dart';
import 'shell_navegacion.dart';

/// Rutas de la aplicación en un solo lugar, para no repetir cadenas sueltas.
class Rutas {
  const Rutas._();

  static const String cargando = '/';
  static const String login = '/login';
  static const String solicitudes = '/solicitudes';
  static const String notificaciones = '/notificaciones';
  static const String perfil = '/perfil';
}

/// Puente entre Riverpod y go_router: notifica al router cada vez que cambia
/// el estado de la sesión para que vuelva a evaluar la redirección.
class _RefrescoDeSesion extends ChangeNotifier {
  _RefrescoDeSesion(Ref ref) {
    ref.listen<EstadoSesion>(
      authControllerProvider,
      (anterior, actual) => notifyListeners(),
    );
  }
}

final appRouterProvider = Provider<GoRouter>((ref) {
  final refresco = _RefrescoDeSesion(ref);
  ref.onDispose(refresco.dispose);

  return GoRouter(
    initialLocation: Rutas.cargando,
    refreshListenable: refresco,
    redirect: (context, state) {
      final sesion = ref.read(authControllerProvider);
      final ubicacion = state.matchedLocation;

      // Mientras se valida el token guardado se muestra la pantalla de carga.
      if (sesion is SesionCargando) {
        return ubicacion == Rutas.cargando ? null : Rutas.cargando;
      }

      // Sin sesión, toda ruta lleva al login.
      if (sesion is SinSesion) {
        return ubicacion == Rutas.login ? null : Rutas.login;
      }

      // Con sesión activa no tiene sentido quedarse en login ni en la carga;
      // la pestaña inicial es el listado de solicitudes.
      if (ubicacion == Rutas.login || ubicacion == Rutas.cargando) {
        return Rutas.solicitudes;
      }
      return null;
    },
    routes: [
      GoRoute(
        path: Rutas.cargando,
        builder: (context, state) => const _PantallaDeCarga(),
      ),
      GoRoute(
        path: Rutas.login,
        builder: (context, state) => const LoginScreen(),
      ),
      // Cada rama conserva su propia pila de navegación y su estado.
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) =>
            ShellNavegacion(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: Rutas.solicitudes,
                builder: (context, state) => const MisSolicitudesScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: Rutas.notificaciones,
                builder: (context, state) => const NotificacionesScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: Rutas.perfil,
                builder: (context, state) => const PerfilScreen(),
              ),
            ],
          ),
        ],
      ),
    ],
  );
});

/// Se muestra durante el arranque, mientras `GET /auth/me` decide si la sesión
/// guardada sigue siendo válida.
class _PantallaDeCarga extends StatelessWidget {
  const _PantallaDeCarga();

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;

    return Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.school_outlined, size: 64, color: esquema.primary),
            const SizedBox(height: 24),
            const SizedBox.square(
              dimension: 24,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          ],
        ),
      ),
    );
  }
}
