import 'package:flutter/material.dart';

/// Aling tab ang bukas: 0 Home, 1 Menu, 2 Cart, 3 Order, 4 Profile
final ValueNotifier<int> rootTab = ValueNotifier<int>(0);

/// Root scaffold key used by the home hamburger button to open the customer drawer.
final GlobalKey<ScaffoldState> rootScaffoldKey = GlobalKey<ScaffoldState>();

/// Kahilingan mula sa Home papunta sa Menu: {'category': int?, 'search': String?}
final ValueNotifier<Map<String, dynamic>?> menuRequest =
    ValueNotifier<Map<String, dynamic>?>(null);
