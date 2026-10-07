import 'package:flutter/foundation.dart';

import '../core/api.dart';

class Favorites extends ChangeNotifier {
  Favorites._();
  static final Favorites I = Favorites._();

  final Set<int> ids = {};
  List<Map<String, dynamic>> items = [];

  bool has(int id) => ids.contains(id);

  Future<void> load() async {
    try {
      items = await Api.I.favorites();
      ids
        ..clear()
        ..addAll(items.map((p) => p['id'] as int));
      notifyListeners();
    } catch (_) {}
  }

  Future<void> toggle(Map<String, dynamic> product) async {
    final id = product['id'] as int;
    final had = ids.contains(id);
    if (had) {
      ids.remove(id);
      items.removeWhere((p) => p['id'] == id);
    } else {
      ids.add(id);
      items.add(product);
    }
    notifyListeners();
    try {
      if (had) {
        await Api.I.removeFavorite(id);
      } else {
        await Api.I.addFavorite(id);
      }
    } catch (_) {
      await load(); // ibalik sa tunay na estado kung nag-fail
    }
  }

  void clear() {
    ids.clear();
    items = [];
    notifyListeners();
  }
}
