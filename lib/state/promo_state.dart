import 'package:flutter/foundation.dart';

/// Ang promo na pinindot ng customer sa Home. Awtomatiko itong ilalagay sa checkout.
class SelectedPromo extends ChangeNotifier {
  SelectedPromo._();
  static final SelectedPromo I = SelectedPromo._();

  Map<String, dynamic>? promo;

  String? get code => promo?['code']?.toString();

  int? get productId {
    final p = promo?['product'];
    return p is Map ? (p['id'] as num?)?.toInt() : null;
  }

  bool get isBogo => promo?['type'] == 'bogo';

  /// Ilang piraso ang kailangan para sa Buy X Get Y (bayad + libre)
  int get bogoNeed => ((promo?['buy_qty'] as num?)?.toInt() ?? 1) + ((promo?['free_qty'] as num?)?.toInt() ?? 1);

  void select(Map<String, dynamic> p) {
    promo = Map<String, dynamic>.from(p);
    notifyListeners();
  }

  void clear() {
    promo = null;
    notifyListeners();
  }
}
