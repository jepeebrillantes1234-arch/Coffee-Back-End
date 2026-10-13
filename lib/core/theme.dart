import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

const espresso = Color(0xFF4B2E2B);
const cream = Color(0xFFFFFFFF);
const caramel = Color(0xFFC8843C);
const success = Color(0xFF3C8D5A);

ThemeData appTheme([ThemeMode mode = ThemeMode.light]) => ThemeData(
  useMaterial3: true,
  brightness: mode == ThemeMode.dark ? Brightness.dark : Brightness.light,
  colorScheme: ColorScheme.fromSeed(
    seedColor: espresso,
    primary: espresso,
    secondary: caramel,
    brightness: mode == ThemeMode.dark ? Brightness.dark : Brightness.light,
  ),
  scaffoldBackgroundColor: mode == ThemeMode.dark
      ? const Color(0xFF171311)
      : cream,
  cardTheme: CardThemeData(
    color: mode == ThemeMode.dark ? const Color(0xFF241E1A) : Colors.white,
    surfaceTintColor: Colors.transparent,
  ),
  appBarTheme: AppBarTheme(
    backgroundColor: mode == ThemeMode.dark
        ? const Color(0xFF211B17)
        : Colors.white,
    foregroundColor: mode == ThemeMode.dark ? Colors.white : espresso,
    surfaceTintColor: Colors.transparent,
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

class AppThemeController extends ChangeNotifier {
  AppThemeController._();

  static final AppThemeController I = AppThemeController._();
  static const _preferenceKey = 'app_theme_mode';
  ThemeMode _mode = ThemeMode.light;
  ThemeMode get mode => _mode;

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    _mode = prefs.getString(_preferenceKey) == 'dark'
        ? ThemeMode.dark
        : ThemeMode.light;
    notifyListeners();
  }

  Future<void> setMode(ThemeMode mode) async {
    if (_mode == mode) return;
    _mode = mode;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_preferenceKey, mode == ThemeMode.dark ? 'dark' : 'light');
  }
}

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
