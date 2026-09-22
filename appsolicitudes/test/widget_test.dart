import 'package:appsolicitudes/app.dart';
import 'package:appsolicitudes/core/network/api_client.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'support/token_storage_falso.dart';

void main() {
  testWidgets('sin token guardado la aplicacion muestra el login',
      (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          tokenStorageProvider.overrideWithValue(TokenStorageFalso()),
        ],
        child: const CampusConnectApp(),
      ),
    );

    // Primer fotograma: todavía se está resolviendo si hay sesión.
    expect(find.byType(CircularProgressIndicator), findsOneWidget);

    // Resuelta la lectura del token, el guard redirige al login.
    await tester.pumpAndSettle();

    expect(find.text('Iniciar sesión'), findsOneWidget);
    expect(find.text('Correo institucional'), findsOneWidget);
    expect(find.text('Contraseña'), findsOneWidget);
  });

  testWidgets('el formulario exige correo y contrasena', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          tokenStorageProvider.overrideWithValue(TokenStorageFalso()),
        ],
        child: const CampusConnectApp(),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.widgetWithText(FilledButton, 'Iniciar sesión'));
    await tester.pump();

    expect(find.text('Ingresa tu correo institucional'), findsOneWidget);
    expect(find.text('Ingresa tu contraseña'), findsOneWidget);
  });

  testWidgets('un correo mal formado no pasa la validacion local',
      (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          tokenStorageProvider.overrideWithValue(TokenStorageFalso()),
        ],
        child: const CampusConnectApp(),
      ),
    );
    await tester.pumpAndSettle();

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Correo institucional'),
      'correo-sin-arroba',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Contraseña'),
      'secreto123',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Iniciar sesión'));
    await tester.pump();

    expect(find.text('El correo no tiene un formato válido'), findsOneWidget);
  });

  testWidgets('el boton alterna la visibilidad de la contrasena',
      (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          tokenStorageProvider.overrideWithValue(TokenStorageFalso()),
        ],
        child: const CampusConnectApp(),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byIcon(Icons.visibility_outlined), findsOneWidget);

    await tester.tap(find.byIcon(Icons.visibility_outlined));
    await tester.pump();

    expect(find.byIcon(Icons.visibility_off_outlined), findsOneWidget);
  });
}
