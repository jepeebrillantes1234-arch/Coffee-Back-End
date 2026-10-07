import 'package:flutter/material.dart';

import 'core/api.dart';
import 'core/role_home.dart';
import 'core/theme.dart';
import 'screens/auth_screen.dart';
import 'screens/splash_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Api.I.init();
  runApp(const KjmApp());
}

class KjmApp extends StatelessWidget {
  const KjmApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'KJM Coffee Brew',
      debugShowCheckedModeBanner: false,
      theme: appTheme(),
      home: SplashScreen(
        nextScreen: Api.I.loggedIn ? homeForRole(Api.I.role) : const AuthScreen(),
      ),
    );
  }
}
