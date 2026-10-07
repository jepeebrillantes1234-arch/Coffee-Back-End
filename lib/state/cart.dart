import 'package:flutter/foundation.dart';

class CartLine {
  final int productId;
  final String name;
  final String? imageUrl;
  final double unitPrice;
  final List<int> optionIds;
  final List<String> optionNames;
  final String? notes;
  int qty;

  CartLine({
    required this.productId,
    required this.name,
    required this.imageUrl,
    required this.unitPrice,
    required this.optionIds,
    required this.optionNames,
    this.notes,
    this.qty = 1,
  });

  double get total => unitPrice * qty;

  String get key => '$productId|${([...optionIds]..sort()).join(',')}|${notes ?? ''}';

  Map<String, dynamic> toPayload() => {
        'product_id': productId,
        'quantity': qty,
        'option_ids': optionIds,
        if (notes != null && notes!.isNotEmpty) 'notes': notes,
      };
}

class Cart extends ChangeNotifier {
  Cart._();
  static final Cart I = Cart._();

  final List<CartLine> lines = [];

  int get count => lines.fold(0, (s, l) => s + l.qty);
  double get subtotal => lines.fold(0.0, (s, l) => s + l.total);

  void add(CartLine line) {
    final i = lines.indexWhere((l) => l.key == line.key);
    if (i >= 0) {
      lines[i].qty += line.qty;
    } else {
      lines.add(line);
    }
    notifyListeners();
  }

  void setQty(int index, int qty) {
    if (qty <= 0) {
      lines.removeAt(index);
    } else {
      lines[index].qty = qty;
    }
    notifyListeners();
  }

  void clear() {
    lines.clear();
    notifyListeners();
  }
}
