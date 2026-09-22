import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// Contenedor de las tres secciones principales.
///
/// Recibe el `navigationShell` de go_router, que conserva el estado de cada
/// pestaña: al volver a Solicitudes, la lista sigue en la misma posición.
class ShellNavegacion extends StatelessWidget {
  const ShellNavegacion({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  void _irA(int indice) {
    navigationShell.goBranch(
      indice,
      // Volver a tocar la pestaña activa regresa a su raíz.
      initialLocation: indice == navigationShell.currentIndex,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: navigationShell.currentIndex,
        onDestinationSelected: _irA,
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.assignment_outlined),
            selectedIcon: Icon(Icons.assignment),
            label: 'Solicitudes',
          ),
          NavigationDestination(
            icon: Icon(Icons.notifications_outlined),
            selectedIcon: Icon(Icons.notifications),
            label: 'Notificaciones',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline),
            selectedIcon: Icon(Icons.person),
            label: 'Perfil',
          ),
        ],
      ),
    );
  }
}
