import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import '../state/cart.dart';
import '../state/promo_state.dart';

class ProductScreen extends StatefulWidget {
  final int id;
  const ProductScreen({super.key, required this.id});

  @override
  State<ProductScreen> createState() => _ProductScreenState();
}

class _ProductScreenState extends State<ProductScreen> {
  Map<String, dynamic>? _p;
  bool _loading = true;
  String? _error;
  final Map<int, Set<int>> _sel = {}; // group_id -> option_ids
  int _qty = 1;
  Map<String, dynamic>? _reviews;
  final _notes = TextEditingController();

  List<Map<String, dynamic>> get _groups => _p == null ? [] : mapList(_p!['option_groups'] ?? []);

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final p = await Api.I.product(widget.id);
      if (!mounted) return;
      Api.I.productReviews(widget.id).then((r) {
        if (mounted) setState(() => _reviews = r);
      }).catchError((_) {});
      setState(() {
        _p = p;
        _loading = false;
        // Buy X Get Y: ilagay agad ang kailangang dami
        if (SelectedPromo.I.isBogo && SelectedPromo.I.productId == (p['id'] as num).toInt()) {
          _qty = SelectedPromo.I.bogoNeed;
        }
        // Paunang pili: unang option ng bawat required na single-choice group
        for (final g in _groups) {
          final opts = mapList(g['options'] ?? []);
          _sel[g['id'] as int] = {};
          if (g['is_required'] == true && g['is_multiple'] != true && opts.isNotEmpty) {
            _sel[g['id'] as int]!.add(opts.first['id'] as int);
          }
        }
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  List<Map<String, dynamic>> get _chosen {
    final out = <Map<String, dynamic>>[];
    for (final g in _groups) {
      for (final o in mapList(g['options'] ?? [])) {
        if (_sel[g['id'] as int]?.contains(o['id'] as int) == true) out.add(o);
      }
    }
    return out;
  }

  double get _unit => dbl(_p?['base_price']) + _chosen.fold(0.0, (s, o) => s + dbl(o['extra_price']));

  void _toggle(Map<String, dynamic> g, Map<String, dynamic> o) {
    final gid = g['id'] as int;
    final oid = o['id'] as int;
    final set = _sel[gid] ?? <int>{};
    setState(() {
      if (g['is_multiple'] == true) {
        if (set.contains(oid)) {
          set.remove(oid);
        } else if (set.length < (g['max_select'] as int)) {
          set.add(oid);
        } else {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Hanggang ${g['max_select']} lang ang puwede sa ${g['name']}.')));
        }
      } else {
        set
          ..clear()
          ..add(oid);
      }
      _sel[gid] = set;
    });
  }

  void _add() {
    for (final g in _groups) {
      if (g['is_required'] == true && (_sel[g['id'] as int] ?? {}).isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Pumili ng ${g['name']}.')));
        return;
      }
    }
    final chosen = _chosen;
    Cart.I.add(CartLine(
      productId: _p!['id'] as int,
      name: '${_p!['name']}',
      imageUrl: _p!['image_url']?.toString(),
      unitPrice: _unit,
      optionIds: chosen.map((o) => o['id'] as int).toList(),
      optionNames: chosen.map((o) => '${o['name']}').toList(),
      notes: _notes.text.trim().isEmpty ? null : _notes.text.trim(),
      qty: _qty,
    ));
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Naidagdag sa cart')));
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final p = _p;
    return Scaffold(
      appBar: AppBar(title: Text(p == null ? 'Product' : '${p['name']}')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : p == null
              ? Center(child: Text(_error ?? 'May error.'))
              : Column(children: [
                  Expanded(
                    child: ListView(children: [
                      SizedBox(height: 220, width: double.infinity, child: NetImage(p['image_url']?.toString())),
                      Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('${p['name']}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700)),
                          if ((p['description'] ?? '').toString().isNotEmpty)
                            Padding(padding: const EdgeInsets.only(top: 6), child: Text('${p['description']}', style: const TextStyle(color: Colors.black54))),
                          const SizedBox(height: 16),
                          _promoTag(p),
                          for (final g in _groups) _group(g),
                          const SizedBox(height: 8),
                          TextField(
                            controller: _notes,
                            decoration: const InputDecoration(labelText: 'Note (opsyonal)', border: OutlineInputBorder(), filled: true, fillColor: Colors.white),
                          ),
                          if (_reviews != null) _reviewsBox(),
                        ]),
                      ),
                    ]),
                  ),
                  SafeArea(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                      child: Row(children: [
                        IconButton.outlined(onPressed: _qty > 1 ? () => setState(() => _qty--) : null, icon: const Icon(Icons.remove)),
                        Padding(padding: const EdgeInsets.symmetric(horizontal: 14), child: Text('$_qty', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600))),
                        IconButton.outlined(onPressed: () => setState(() => _qty++), icon: const Icon(Icons.add)),
                        const SizedBox(width: 12),
                        Expanded(child: FilledButton(onPressed: _add, child: Text('Idagdag · ${peso(_unit * _qty)}'))),
                      ]),
                    ),
                  ),
                ]),
    );
  }

  Widget _promoTag(Map<String, dynamic> p) {
    return ListenableBuilder(
      listenable: SelectedPromo.I,
      builder: (_, __) {
        final sp = SelectedPromo.I.promo;
        if (sp == null) return const SizedBox.shrink();
        final pid = SelectedPromo.I.productId;
        if (pid != null && pid != (p['id'] as num).toInt()) return const SizedBox.shrink();

        final text = pid == null
            ? '🎟 Promo ${sp['code']} ay awtomatikong gagamitin sa checkout.'
            : SelectedPromo.I.isBogo
                ? '🎟 ${sp['headline'] ?? sp['code']}: nakalagay na ang ${SelectedPromo.I.bogoNeed} piraso. Awtomatiko ang diskwento sa checkout.'
                : '🎟 ${sp['headline'] ?? sp['code']} sa produktong ito (1 piraso lang). Awtomatiko sa checkout.';
        return Container(
          width: double.infinity,
          margin: const EdgeInsets.only(bottom: 14),
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: success.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: success.withValues(alpha: 0.4)),
          ),
          child: Text(text, style: const TextStyle(fontSize: 12.5)),
        );
      },
    );
  }

  Widget _reviewsBox() {
    final r = _reviews!;
    final items = mapList(r['items'] ?? []);
    final count = (r['count'] as num?)?.toInt() ?? 0;
    return Padding(
      padding: const EdgeInsets.only(top: 22),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(
          count == 0 ? 'Mga Review' : 'Mga Review · ★ ${dbl(r['avg']).toStringAsFixed(1)} ($count)',
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
        ),
        const SizedBox(height: 6),
        if (items.isEmpty) const Text('Wala pang review.', style: TextStyle(color: Colors.black54)),
        for (final it in items)
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Text('${it['name']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                  const SizedBox(width: 8),
                  Text('★' * (it['rating'] as int), style: const TextStyle(color: Colors.amber)),
                  const Spacer(),
                  Text('${it['date']}', style: const TextStyle(fontSize: 11, color: Colors.black45)),
                ]),
                if ((it['comment'] ?? '').toString().isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Text('${it['comment']}')),
                if ((it['reply'] ?? '').toString().isNotEmpty)
                  Padding(padding: const EdgeInsets.only(top: 6), child: Text('☕ Shop: ${it['reply']}', style: const TextStyle(fontSize: 12, color: caramel))),
              ]),
            ),
          ),
      ]),
    );
  }

  Widget _group(Map<String, dynamic> g) {
    final opts = mapList(g['options'] ?? []);
    final gid = g['id'] as int;
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Text('${g['name']}', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
          const SizedBox(width: 8),
          Text(g['is_required'] == true ? 'Kailangan' : 'Opsyonal', style: TextStyle(fontSize: 12, color: g['is_required'] == true ? caramel : Colors.black45)),
        ]),
        const SizedBox(height: 6),
        Wrap(spacing: 8, runSpacing: 4, children: [
          for (final o in opts)
            FilterChip(
              label: Text('${o['name']}${dbl(o['extra_price']) > 0 ? '  +₱${dbl(o['extra_price']).toStringAsFixed(0)}' : ''}'),
              selected: _sel[gid]?.contains(o['id'] as int) == true,
              onSelected: (_) => _toggle(g, o),
              selectedColor: caramel.withValues(alpha: 0.3),
            ),
        ]),
      ]),
    );
  }
}
