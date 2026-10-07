import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/theme.dart';
import '../core/util.dart';
import 'payment_screen.dart';

class OrderDetailScreen extends StatefulWidget {
  final int id;
  const OrderDetailScreen({super.key, required this.id});

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  Map<String, dynamic>? _o;
  Map<String, dynamic>? _t;
  bool _loading = true;
  String? _error;
  Timer? _poll;
  int _rating = 5;
  final _comment = TextEditingController();
  bool _busy = false;

  static const _final = ['completed', 'rejected', 'cancelled'];

  @override
  void initState() {
    super.initState();
    _load();
    _poll = Timer.periodic(const Duration(seconds: 5), (_) {
      final s = _o?['status'];
      if (s == null || !_final.contains(s)) _load(silent: true);
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    _comment.dispose();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    try {
      final o = await Api.I.order(widget.id);
      final t = await Api.I.tracking(widget.id);
      if (!mounted) return;
      setState(() {
        _o = o;
        _t = t;
        _loading = false;
        _error = null;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      if (!silent) {
        setState(() {
          _error = e.message;
          _loading = false;
        });
      }
    }
  }

  Future<void> _cancel() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('I-cancel ang order?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Hindi')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Oo, i-cancel')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await Api.I.cancel(widget.id);
      await _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _pay() async {
    setState(() => _busy = true);
    try {
      final fresh = await Api.I.payOrder(widget.id);
      if (!mounted) return;
      if (fresh['status'] != 'awaiting_payment') {
        await _load();
        return;
      }
      await Navigator.of(context).push(MaterialPageRoute(builder: (_) => PaymentScreen(order: fresh)));
      await _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _review() async {
    setState(() => _busy = true);
    try {
      await Api.I.review(widget.id, _rating, _comment.text.trim());
      await _load();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Salamat sa review!')));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final o = _o;
    return Scaffold(
      appBar: AppBar(title: Text(o == null ? 'Order' : '${o['order_number']}')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : o == null
              ? Center(child: Text(_error ?? 'May error.'))
              : _body(o, _t ?? {}),
    );
  }

  Widget _body(Map<String, dynamic> o, Map<String, dynamic> t) {
    final status = '${o['status']}';
    final type = '${o['order_type']}';
    final isDelivery = type == 'delivery';
    final rider = t['rider'] == null ? null : Map<String, dynamic>.from(t['rider'] as Map);
    final dest = Map<String, dynamic>.from((t['destination'] ?? {}) as Map);
    final shop = Map<String, dynamic>.from((t['shop'] ?? {}) as Map);
    final timeline = mapList(t['timeline'] ?? []);
    final items = mapList(o['items'] ?? []);

    LatLng? pt(Map<String, dynamic> m) =>
        (m['lat'] == null || m['lng'] == null) ? null : LatLng(dbl(m['lat']), dbl(m['lng']));
    final destPt = pt(dest);
    final shopPt = (dbl(shop['lat']) == 0 && dbl(shop['lng']) == 0) ? null : pt(shop);
    final riderPt = rider == null ? null : pt(rider);

    final points = <LatLng>[if (destPt != null) destPt, if (riderPt != null) riderPt, if (shopPt != null) shopPt];

    return ListView(padding: const EdgeInsets.all(14), children: [
      Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: statusColor(status), borderRadius: BorderRadius.circular(14)),
        child: Row(children: [
          Expanded(child: Text(statusLabel(status, type), style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w600))),
          if (rider?['eta_min'] != null) Text('~${rider!['eta_min']} min', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
        ]),
      ),
      const SizedBox(height: 12),

      // MAPA
      if (isDelivery && destPt != null && !_final.contains(status)) ...[
        SizedBox(
          height: 260,
          child: ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: FlutterMap(
              key: ValueKey(riderPt != null),
              options: MapOptions(
                initialCenter: destPt,
                initialZoom: 15,
                initialCameraFit: points.length > 1
                    ? CameraFit.bounds(bounds: LatLngBounds.fromPoints(points), padding: const EdgeInsets.all(48), maxZoom: 16)
                    : null,
              ),
              children: [
                TileLayer(urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', userAgentPackageName: 'com.kjmcoffeebrew.app'),
                MarkerLayer(markers: [
                  if (shopPt != null) Marker(point: shopPt, width: 40, height: 40, child: const Text('â˜•', style: TextStyle(fontSize: 28))),
                  Marker(point: destPt, width: 44, height: 44, alignment: Alignment.topCenter, child: const Text('ðŸ“', style: TextStyle(fontSize: 32))),
                  if (riderPt != null) Marker(point: riderPt, width: 44, height: 44, child: const Text('ðŸ›µ', style: TextStyle(fontSize: 32))),
                ]),
              ],
            ),
          ),
        ),
        const SizedBox(height: 6),
        Text(
          riderPt != null ? 'ðŸ›µ Live ang lokasyon ng rider (nag-a-update tuwing ilang segundo)' : 'Lalabas ang rider sa mapa kapag papunta na siya sa iyo.',
          style: const TextStyle(fontSize: 12, color: Colors.black54),
        ),
        const SizedBox(height: 12),
      ],

      if (rider != null)
        Card(
          child: ListTile(
            leading: const CircleAvatar(child: Text('ðŸ›µ')),
            title: Text('${rider['name'] ?? 'Rider'}'),
            subtitle: Text(statusLabel('${rider['status']}'.replaceAll('accepted', 'rider_assigned'), type)),
            trailing: rider['phone'] == null
                ? null
                : IconButton(icon: const Icon(Icons.call, color: success), onPressed: () => launchUrl(Uri.parse('tel:${rider['phone']}'))),
          ),
        ),

      Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Timeline', style: TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            for (final l in timeline)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: Row(children: [
                  Icon(Icons.check_circle, size: 16, color: statusColor('${l['status']}')),
                  const SizedBox(width: 8),
                  Expanded(child: Text(statusLabel('${l['status']}', type))),
                  Text(timeText(l['at']), style: const TextStyle(fontSize: 12, color: Colors.black54)),
                ]),
              ),
          ]),
        ),
      ),

      Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (isDelivery) ...[
              Text('ðŸ“ ${o['delivery_address'] ?? ''}'),
              const Divider(),
            ],
            for (final it in items)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${it['qty']}Ã— ${it['name']}'),
                      if ((it['options'] as List).isNotEmpty) Text((it['options'] as List).join(', '), style: const TextStyle(fontSize: 12, color: Colors.black54)),
                    ]),
                  ),
                  Text(peso(dbl(it['line_total']))),
                ]),
              ),
            const Divider(),
            _row('Subtotal', peso(dbl(o['subtotal']))),
            if (dbl(o['discount']) > 0) _row('Diskwento', '-${peso(dbl(o['discount']))}'),
            if (isDelivery) _row('Delivery fee', peso(dbl(o['delivery_fee']))),
            _row('Total', peso(dbl(o['total'])), bold: true),
            const SizedBox(height: 4),
            Text('${payLabel(o['payment_method'])} Â· ${'${o['payment_status']}'.toUpperCase()}',
                style: const TextStyle(fontSize: 12, color: Colors.black54)),
            if (o['reject_reason'] != null) Padding(padding: const EdgeInsets.only(top: 6), child: Text('Dahilan: ${o['reject_reason']}', style: const TextStyle(color: Colors.red))),
          ]),
        ),
      ),

      if (o['can_pay'] == true)
        Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: FilledButton(
            onPressed: _busy ? null : _pay,
            child: Text('Magbayad sa GCash Â· ${peso(dbl(o['total']))}'),
          ),
        ),

      if (o['can_cancel'] == true)
        OutlinedButton(
          style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48), foregroundColor: Colors.red),
          onPressed: _cancel,
          child: const Text('I-cancel ang order'),
        ),

      if ((status == 'delivered' || status == 'completed') && o['has_review'] != true)
        Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(children: [
              const Text('Kumusta ang order mo?', style: TextStyle(fontWeight: FontWeight.w600)),
              Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                for (var i = 1; i <= 5; i++)
                  IconButton(
                    onPressed: () => setState(() => _rating = i),
                    icon: Icon(i <= _rating ? Icons.star : Icons.star_border, color: Colors.amber, size: 32),
                  ),
              ]),
              TextField(controller: _comment, decoration: const InputDecoration(hintText: 'Komento (opsyonal)', border: OutlineInputBorder())),
              const SizedBox(height: 10),
              FilledButton(onPressed: _busy ? null : _review, child: const Text('Ipadala ang review')),
            ]),
          ),
        ),
      const SizedBox(height: 24),
    ]);
  }

  Widget _row(String l, String v, {bool bold = false}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text(l, style: TextStyle(fontWeight: bold ? FontWeight.w700 : null)),
          Text(v, style: TextStyle(fontWeight: bold ? FontWeight.w700 : null, color: bold ? caramel : null)),
        ]),
      );
}

