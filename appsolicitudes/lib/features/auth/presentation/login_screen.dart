import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'auth_controller.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formulario = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();

  bool _enviando = false;
  bool _mostrarPassword = false;

  /// Errores por campo devueltos por el servidor en un 422.
  Map<String, String> _erroresDelServidor = const {};

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _enviar() async {
    // Se limpian los errores del intento anterior antes de validar.
    setState(() => _erroresDelServidor = const {});
    if (!_formulario.currentState!.validate()) return;

    setState(() => _enviando = true);

    final error = await ref.read(authControllerProvider.notifier).iniciarSesion(
          email: _email.text,
          password: _password.text,
        );

    if (!mounted) return;
    setState(() {
      _enviando = false;
      _erroresDelServidor = error?.erroresPorCampo ?? const {};
    });

    if (error != null) {
      // Revalida para pintar bajo cada campo lo que devolvió el servidor.
      _formulario.currentState!.validate();
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(error.mensaje)));
    }
    // Si no hubo error, el guard del router navega solo al cambiar el estado.
  }

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    final textos = Theme.of(context).textTheme;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
            child: ConstrainedBox(
              // Evita que el formulario se estire en tablets.
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formulario,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Icon(
                      Icons.school_outlined,
                      size: 56,
                      color: esquema.primary,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Campus Connect',
                      textAlign: TextAlign.center,
                      style: textos.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Registra y da seguimiento a tus solicitudes',
                      textAlign: TextAlign.center,
                      style: textos.bodyMedium?.copyWith(
                        color: esquema.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 32),
                    TextFormField(
                      controller: _email,
                      enabled: !_enviando,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.email],
                      decoration: const InputDecoration(
                        labelText: 'Correo institucional',
                        prefixIcon: Icon(Icons.alternate_email),
                      ),
                      validator: _validarEmail,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _password,
                      enabled: !_enviando,
                      obscureText: !_mostrarPassword,
                      textInputAction: TextInputAction.done,
                      autofillHints: const [AutofillHints.password],
                      onFieldSubmitted: (_) => _enviando ? null : _enviar(),
                      decoration: InputDecoration(
                        labelText: 'Contraseña',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          onPressed: () => setState(
                            () => _mostrarPassword = !_mostrarPassword,
                          ),
                          icon: Icon(
                            _mostrarPassword
                                ? Icons.visibility_off_outlined
                                : Icons.visibility_outlined,
                          ),
                          tooltip: _mostrarPassword
                              ? 'Ocultar contraseña'
                              : 'Mostrar contraseña',
                        ),
                      ),
                      validator: _validarPassword,
                    ),
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: _enviando ? null : _enviar,
                      child: _enviando
                          ? const SizedBox.square(
                              dimension: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Iniciar sesión'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  String? _validarEmail(String? valor) {
    final servidor = _erroresDelServidor['email'];
    if (servidor != null) return servidor;

    final texto = valor?.trim() ?? '';
    if (texto.isEmpty) return 'Ingresa tu correo institucional';
    if (!texto.contains('@') || !texto.contains('.')) {
      return 'El correo no tiene un formato válido';
    }
    return null;
  }

  String? _validarPassword(String? valor) {
    final servidor = _erroresDelServidor['password'];
    if (servidor != null) return servidor;

    if (valor == null || valor.isEmpty) return 'Ingresa tu contraseña';
    return null;
  }
}
