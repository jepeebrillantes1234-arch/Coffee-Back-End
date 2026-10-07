import 'package:flutter/material.dart';

import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import '../state/cart.dart';
import '../state/promo_state.dart';
import 'checkout_screen.dart';

class CartScreen extends StatelessWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Cart')),
      body: ListenableBuilder(
        listenable: Cart.I,
        builder: (_, _) {
          final lines = Cart.I.lines;
          if (lines.isEmpty) {
            return const Center(child: Text('Walang laman ang cart.\nMag-order ng kape sa Menu ☕', textAlign: TextAlign.center));
          }
          return Column(children: [
            const _PromoNote(),
            Expanded(
              child: ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: lines.length,
                itemBuilder: (_, i) {
                  final l = lines[i];
                  return Card(
                    child: Padding(
                      padding: const EdgeInsets.all(10),
                      child: Row(children: [
                        ClipRRect(borderRadius: BorderRadius.circular(10), child: NetImage(l.imageUrl, width: 64, height: 64)),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(l.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                            if (l.optionNames.isNotEmpty) Text(l.optionNames.join(', '), style: const TextStyle(fontSize: 12, color: Colors.black54)),
                            if (l.notes != null) Text('Note: ${l.notes}', style: const TextStyle(fontSize: 12, color: Colors.black45)),
                            Text(peso(l.total), style: const TextStyle(color: caramel, fontWeight: FontWeight.w700)),
                          ]),
                        ),
                        Column(children: [
                          Row(children: [
                            IconButton(visualDensity: VisualDensity.compact, onPressed: () => Cart.I.setQty(i, l.qty - 1), icon: const Icon(Icons.remove_circle_outline)),
                            Text('${l.qty}', style: const TextStyle(fontWeight: FontWeight.w600)),
                            IconButton(visualDensity: VisualDensity.compact, onPressed: () => Cart.I.setQty(i, l.qty + 1), icon: const Icon(Icons.add_circle_outline)),
                          ]),
                        ]),
                      ]),
                    ),
                  );
                },
              ),
            ),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    const Text('Subtotal', style: TextStyle(fontSize: 16)),
                    Text(peso(Cart.I.subtotal), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                  ]),
                  const SizedBox(height: 10),
                  FilledButton(
                    onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const CheckoutScreen())),
                    child: const Text('Mag-checkout'),
                  ),
                ]),
              ),
            ),
          ]);
        },
      ),
    );
  }
}

class _PromoNote extends StatelessWidget {
  const _PromoNote();

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: SelectedPromo.I,
      builder: (_, _) {
        final p = SelectedPromo.I.promo;
        if (p == null) return const SizedBox.shrink();
        final prod = p['product'] is Map ? (p['product'] as Map)['name'] : null;
        return Container(
          width: double.infinity,
          margin: const EdgeInsets.fromLTRB(12, 12, 12, 0),
          padding: const EdgeInsets.fromLTRB(12, 4, 4, 4),
          decoration: BoxDecoration(
            color: success.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: success.withValues(alpha: 0.4)),
          ),
          child: Row(children: [
            const Text('🎟'),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Promo ${p['code']} ay awtomatikong gagamitin sa checkout${prod != null ? ' (para sa $prod)' : ''}.',
                style: const TextStyle(fontSize: 12.5),
              ),
            ),
            IconButton(visualDensity: VisualDensity.compact, icon: const Icon(Icons.close, size: 18), onPressed: SelectedPromo.I.clear),
          ]),
        );
      },
    );
  }
}
