import 'package:flutter/material.dart';

const espresso = Color(0xFF4B2E2B);
const cream = Color(0xFFFFFFFF);
const caramel = Color(0xFFC8843C);
const success = Color(0xFF3C8D5A);

ThemeData appTheme() => ThemeData(
  useMaterial3: true,
  colorScheme: ColorScheme.fromSeed(
    seedColor: espresso,
    primary: espresso,
    secondary: caramel,
  ),
  scaffoldBackgroundColor: cream,
  appBarTheme: const AppBarTheme(
    backgroundColor: Colors.white,
    foregroundColor: espresso,
    surfaceTintColor: Colors.white,
  ),
  filledButtonTheme: FilledButtonThemeData(
    style: FilledButton.styleFrom(
      backgroundColor: espresso,
      foregroundColor: cream,
      minimumSize: const Size.fromHeight(50),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ),
  ),
);

String statusLabel(String s, [String type = 'delivery']) {
  switch (s) {
    case 'awaiting_payment':
      return 'Hinihintay ang bayad';
    case 'pending':
      return 'Naghihintay ng kumpirmasyon';
    case 'confirmed':
      return 'Kinumpirma ng shop';
    case 'preparing':
      return 'Inihahanda ang order';
    case 'ready':
      return type == 'pickup'
          ? 'Handa nang kunin'
          : 'Handa na, naghahanap ng rider';
    case 'rider_assigned':
      return 'May rider na';
    case 'picked_up':
      return 'Kinuha na ng rider';
    case 'on_the_way':
      return 'Papunta na sa iyo';
    case 'delivered':
      return 'Na-deliver na';
    case 'completed':
      return 'Tapos na';
    case 'rejected':
      return 'Tinanggihan ng shop';
    case 'cancelled':
      return 'Na-cancel';
    default:
      return s;
  }
}

Color statusColor(String s) {
  switch (s) {
    case 'awaiting_payment':
    case 'pending':
      return Colors.amber.shade800;
    case 'confirmed':
    case 'preparing':
      return Colors.orange.shade800;
    case 'ready':
    case 'rider_assigned':
    case 'picked_up':
      return Colors.indigo;
    case 'on_the_way':
      return Colors.purple;
    case 'delivered':
    case 'completed':
      return success;
    default:
      return Colors.red.shade700;
  }
}
