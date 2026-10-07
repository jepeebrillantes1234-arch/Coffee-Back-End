import 'package:flutter/foundation.dart';

/// Aling tab ang bukas: 0 Home, 1 Menu, 2 Cart, 3 Order, 4 Profile
final ValueNotifier<int> rootTab = ValueNotifier<int>(0);

/// Kahilingan mula sa Home papunta sa Menu: {'category': int?, 'search': String?}
final ValueNotifier<Map<String, dynamic>?> menuRequest = ValueNotifier<Map<String, dynamic>?>(null);
