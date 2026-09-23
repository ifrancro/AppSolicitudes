import 'package:appsolicitudes/app.dart';
import 'package:appsolicitudes/core/network/api_client.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'support/api_falsa.dart';
import 'support/token_storage_falso.dart';

/// Monta la aplicación con una sesión ya iniciada y la API simulada.
Future<ApiFalsa> _montarConSesion(
  WidgetTester tester, {
  Map<String, RespuestaFalsa>? respuestas,
}) async {
  final api = ApiFalsa({
    '/auth/me': const RespuestaFalsa({'usuario': usuarioDeEjemplo}),
    '/tipos-solicitud': const RespuestaFalsa({'data': tiposDeEjemplo}),
    ...?respuestas,
  });

  final tokenStorage = TokenStorageFalso(tokenInicial: 'token-de-prueba');
  final dio = Dio()..httpClientAdapter = api;

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        tokenStorageProvider.overrideWithValue(tokenStorage),
        apiClientProvider.overrideWithValue(
          ApiClient(tokenStorage: tokenStorage, dio: dio),
        ),
      ],
      child: const CampusConnectApp(),
    ),
  );
  await tester.pumpAndSettle();
  return api;
}

void main() {
  testWidgets('con token valido la sesion se restaura y abre solicitudes',
      (tester) async {
    final api = await _montarConSesion(tester);

    expect(api.rutasLlamadas, contains('/auth/me'));
    expect(find.text('Mis solicitudes'), findsOneWidget);
    expect(find.byType(NavigationBar), findsOneWidget);
  });

  testWidgets('la barra tiene las tres secciones del estudiante',
      (tester) async {
    await _montarConSesion(tester);

    expect(find.text('Solicitudes'), findsOneWidget);
    expect(find.text('Notificaciones'), findsOneWidget);
    expect(find.text('Perfil'), findsOneWidget);
  });

  testWidgets('el catalogo de tipos se consulta al abrir solicitudes',
      (tester) async {
    final api = await _montarConSesion(tester);

    expect(api.rutasLlamadas, contains('/tipos-solicitud'));
    expect(find.textContaining('3 tipos'), findsOneWidget);
  });

  testWidgets('se navega entre pestanas y el perfil muestra al usuario',
      (tester) async {
    await _montarConSesion(tester);

    await tester.tap(find.text('Perfil'));
    await tester.pumpAndSettle();

    expect(find.text('Mi perfil'), findsOneWidget);
    expect(find.text('Ana Lucía Pérez'), findsOneWidget);
    expect(find.text('ana.perez@universidad.edu'), findsOneWidget);
    expect(find.text('Estudiante'), findsOneWidget);

    await tester.tap(find.text('Notificaciones'));
    await tester.pumpAndSettle();

    expect(find.text('No tienes notificaciones'), findsOneWidget);
  });

  testWidgets('el catalogo solo se pide una vez aunque se cambie de pestana',
      (tester) async {
    final api = await _montarConSesion(tester);

    await tester.tap(find.text('Perfil'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Solicitudes'));
    await tester.pumpAndSettle();

    final llamadasAlCatalogo =
        api.rutasLlamadas.where((ruta) => ruta == '/tipos-solicitud').length;
    expect(llamadasAlCatalogo, 1);
  });

  testWidgets('un token invalido devuelve al login', (tester) async {
    final api = ApiFalsa({
      '/auth/me': const RespuestaFalsa(
        {'message': 'No autenticado.'},
        codigo: 401,
      ),
    });
    final tokenStorage = TokenStorageFalso(tokenInicial: 'token-vencido');

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          tokenStorageProvider.overrideWithValue(tokenStorage),
          apiClientProvider.overrideWithValue(
            ApiClient(
              tokenStorage: tokenStorage,
              dio: Dio()..httpClientAdapter = api,
            ),
          ),
        ],
        child: const CampusConnectApp(),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Iniciar sesión'), findsOneWidget);
    // El interceptor debe haber descartado el token que el servidor rechazó.
    expect(tokenStorage.tieneToken, isFalse);
  });

  testWidgets('si el catalogo falla se ofrece reintentar', (tester) async {
    await _montarConSesion(
      tester,
      respuestas: {
        '/tipos-solicitud': const RespuestaFalsa(
          {'message': 'Error interno'},
          codigo: 500,
        ),
      },
    );

    expect(find.text('No se pudo cargar la información'), findsOneWidget);
    expect(find.text('Reintentar'), findsOneWidget);
  });
}
