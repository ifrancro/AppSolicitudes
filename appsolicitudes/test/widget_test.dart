import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:appsolicitudes/app.dart';

void main() {
  testWidgets('la aplicacion arranca mostrando la marca', (tester) async {
    await tester.pumpWidget(const ProviderScope(child: CampusConnectApp()));

    expect(find.text('Campus Connect'), findsOneWidget);
    expect(find.byIcon(Icons.school_outlined), findsOneWidget);
  });
}
