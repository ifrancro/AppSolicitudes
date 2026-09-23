import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'auth_controller.dart';

/// Datos de la cuenta y salida de la sesión.
class PerfilScreen extends ConsumerWidget {
  const PerfilScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final usuario = ref.watch(usuarioActualProvider);
    final esquema = Theme.of(context).colorScheme;
    final textos = Theme.of(context).textTheme;

    if (usuario == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Mi perfil')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Center(
            child: CircleAvatar(
              radius: 40,
              backgroundColor: esquema.primaryContainer,
              child: Text(
                usuario.iniciales,
                style: textos.headlineSmall?.copyWith(
                  color: esquema.onPrimaryContainer,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            usuario.nombre,
            textAlign: TextAlign.center,
            style: textos.titleLarge,
          ),
          const SizedBox(height: 4),
          Center(
            child: Chip(
              label: Text(usuario.rol.etiqueta),
              backgroundColor: esquema.secondaryContainer,
            ),
          ),
          const SizedBox(height: 24),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.alternate_email),
                  title: const Text('Correo'),
                  subtitle: Text(usuario.email),
                ),
                const Divider(),
                ListTile(
                  leading: const Icon(Icons.badge_outlined),
                  title: const Text('Identificador'),
                  subtitle: Text('#${usuario.id}'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          OutlinedButton.icon(
            onPressed: () => _confirmarSalida(context, ref),
            icon: const Icon(Icons.logout),
            label: const Text('Cerrar sesión'),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(48),
              foregroundColor: esquema.error,
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _confirmarSalida(BuildContext context, WidgetRef ref) async {
    final confirmado = await showDialog<bool>(
      context: context,
      builder: (contexto) => AlertDialog(
        title: const Text('Cerrar sesión'),
        content: const Text('¿Seguro que quieres salir de tu cuenta?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(contexto, false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(contexto, true),
            child: const Text('Cerrar sesión'),
          ),
        ],
      ),
    );

    if (confirmado ?? false) {
      // El guard del router devuelve al login al cambiar el estado.
      await ref.read(authControllerProvider.notifier).cerrarSesion();
    }
  }
}
