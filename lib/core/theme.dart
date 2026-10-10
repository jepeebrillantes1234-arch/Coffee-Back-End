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
      return 'Awaiting payment';
    case 'pending':
      return 'Awaiting confirmation';
    case 'confirmed':
      return 'Confirmed by shop';
    case 'preparing':
      return 'Preparing your order';
    case 'ready':
      return type == 'pickup'
          ? 'Ready for pickup'
          : 'Ready, waiting for a rider';
    case 'rider_assigned':
      return 'Rider assigned';
    case 'picked_up':
      return 'Picked up by rider';
    case 'on_the_way':
      return 'On the way';
    case 'delivered':
      return 'Delivered';
    case 'completed':
      return 'Completed';
    case 'rejected':
      return 'Declined by shop';
    case 'cancelled':
      return 'Cancelled';
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
