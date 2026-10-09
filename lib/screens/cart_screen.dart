import 'package:flutter/material.dart';

import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import '../state/cart.dart';
import '../state/promo_state.dart';
import 'checkout_screen.dart';
import 'product_screen.dart';

class CartScreen extends StatelessWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(title: const Text('Cart')),
      body: ListenableBuilder(
        listenable: Cart.I,
        builder: (_, _) {
          final lines = Cart.I.lines;
          if (lines.isEmpty) {
            return const Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    Icons.shopping_bag_outlined,
                    size: 54,
                    color: Color(0xFFB6AAA0),
                  ),
                  SizedBox(height: 12),
                  Text(
                    'Your cart is empty.',
                    style: TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
                  ),
                  SizedBox(height: 4),
                  Text(
                    'Browse the menu and add your favorites.',
                    style: TextStyle(color: Colors.black54),
                  ),
                ],
              ),
            );
          }

          return Column(
            children: [
              const _PromoNote(),
              Expanded(
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
                  itemCount: lines.length,
                  separatorBuilder: (_, _) =>
                      const Divider(height: 20, indent: 58),
                  itemBuilder: (context, index) {
                    final line = lines[index];
                    return Row(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(10),
                          child: NetImage(line.imageUrl, width: 48, height: 58),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                line.name,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                peso(line.unitPrice),
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: Colors.black87,
                                ),
                              ),
                              if (line.optionNames.isNotEmpty)
                                Text(
                                  line.optionNames.join(', '),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 10,
                                    color: Colors.black54,
                                  ),
                                ),
                              if (line.notes != null &&
                                  line.notes!.trim().isNotEmpty)
                                Text(
                                  'Note: ${line.notes}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 10,
                                    color: Colors.black54,
                                  ),
                                ),
                              const SizedBox(height: 7),
                              Container(
                                height: 28,
                                decoration: BoxDecoration(
                                  border: Border.all(
                                    color: const Color(0xFFE7E0DA),
                                  ),
                                  borderRadius: BorderRadius.circular(16),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    _qtyButton(
                                      Icons.remove,
                                      () => Cart.I.setQty(index, line.qty - 1),
                                    ),
                                    SizedBox(
                                      width: 24,
                                      child: Text(
                                        '${line.qty}',
                                        textAlign: TextAlign.center,
                                        style: const TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                    _qtyButton(
                                      Icons.add,
                                      () => Cart.I.setQty(index, line.qty + 1),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 4),
                        Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            IconButton(
                              tooltip: 'Customize item',
                              visualDensity: VisualDensity.compact,
                              onPressed: () => Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => ProductScreen(
                                    id: line.productId,
                                    cartIndex: index,
                                  ),
                                ),
                              ),
                              icon: const Icon(
                                Icons.tune_rounded,
                                size: 17,
                                color: espresso,
                              ),
                            ),
                            IconButton(
                              tooltip: 'Remove item',
                              visualDensity: VisualDensity.compact,
                              onPressed: () => Cart.I.setQty(index, 0),
                              icon: const Icon(
                                Icons.delete_outline_rounded,
                                size: 19,
                                color: Color(0xFF7C6C62),
                              ),
                            ),
                          ],
                        ),
                      ],
                    );
                  },
                ),
              ),
              SafeArea(
                top: false,
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.fromLTRB(18, 13, 18, 14),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    border: Border(top: BorderSide(color: Color(0xFFECE7E2))),
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      _summaryRow('Subtotal', peso(Cart.I.subtotal)),
                      const SizedBox(height: 7),
                      _summaryRow(
                        'Delivery Fee',
                        'Calculated at checkout',
                        muted: true,
                      ),
                      const Divider(height: 20),
                      _summaryRow('Total', peso(Cart.I.subtotal), bold: true),
                      const SizedBox(height: 13),
                      SizedBox(
                        width: double.infinity,
                        height: 48,
                        child: FilledButton(
                          style: FilledButton.styleFrom(
                            backgroundColor: espresso,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const CheckoutScreen(),
                            ),
                          ),
                          child: const Text(
                            'Proceed to Checkout',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  static Widget _qtyButton(IconData icon, VoidCallback onPressed) => SizedBox(
    width: 28,
    height: 26,
    child: IconButton(
      padding: EdgeInsets.zero,
      visualDensity: VisualDensity.compact,
      onPressed: onPressed,
      icon: Icon(icon, size: 15, color: espresso),
    ),
  );

  static Widget _summaryRow(
    String label,
    String value, {
    bool bold = false,
    bool muted = false,
  }) => Row(
    mainAxisAlignment: MainAxisAlignment.spaceBetween,
    children: [
      Text(
        label,
        style: TextStyle(
          fontSize: bold ? 16 : 13,
          fontWeight: bold ? FontWeight.w700 : FontWeight.w400,
          color: muted ? Colors.black54 : Colors.black87,
        ),
      ),
      Text(
        value,
        style: TextStyle(
          fontSize: bold ? 16 : 13,
          fontWeight: bold ? FontWeight.w700 : FontWeight.w400,
          color: muted ? Colors.black54 : Colors.black87,
        ),
      ),
    ],
  );
}

class _PromoNote extends StatelessWidget {
  const _PromoNote();

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: SelectedPromo.I,
      builder: (_, _) {
        final promo = SelectedPromo.I.promo;
        if (promo == null) return const SizedBox.shrink();
        final product = promo['product'] is Map
            ? (promo['product'] as Map)['name']
            : null;
        return Container(
          width: double.infinity,
          margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
          padding: const EdgeInsets.fromLTRB(12, 4, 4, 4),
          decoration: BoxDecoration(
            color: success.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: success.withValues(alpha: 0.4)),
          ),
          child: Row(
            children: [
              const Icon(Icons.local_offer_outlined, color: success, size: 19),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Promo ${promo['code']} will be applied at checkout${product != null ? ' for $product' : ''}.',
                  style: const TextStyle(fontSize: 12),
                ),
              ),
              IconButton(
                visualDensity: VisualDensity.compact,
                icon: const Icon(Icons.close, size: 18),
                onPressed: SelectedPromo.I.clear,
              ),
            ],
          ),
        );
      },
    );
  }
}
