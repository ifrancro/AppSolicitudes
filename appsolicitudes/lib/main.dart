import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';

void main() {
  // ProviderScope envuelve toda la aplicación: es el contenedor donde Riverpod
  // guarda el estado de cada provider.
  runApp(const ProviderScope(child: CampusConnectApp()));
}
