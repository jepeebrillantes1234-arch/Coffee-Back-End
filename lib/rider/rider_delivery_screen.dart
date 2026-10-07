import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/theme.dart';
import 'rider_location_service.dart';
import 'rider_proof_screen.dart';
import 'rider_theme.dart';

double? _n(dynamic v) => v == null ? null : double.tryParse(v.toString());

class RiderDeliveryScreen extends StatefulWidget {
  final int id;
  const RiderDeliveryScreen({super.key, required this.id});

  @override
  State<RiderDeliveryScreen> createState() => _RiderDeliveryScreenState();
}

class _RiderDeliveryScreenState extends State<RiderDeliveryScreen> {
  Map<String, dynamic>? _d;
  bool _loading = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final d = await Api.I.delivery(widget.id);
      if (!mounted) return;
      setState(() {
        _d = d;
        _loading = false;
        _error = null;
      });
      if (d['status'] == 'on_the_way') LocationService.I.start(deliveryId: widget.id);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _act(String name) async {
    setState(() => _busy = true);
    try {
      await Api.I.action(widget.id, name);
      if (name == 'start') await LocationService.I.start(deliveryId: widget.id);
      if (name == 'delivered') await LocationService.I.stop();
      if (!mounted) return;
      if (name == 'decline' || name == 'delivered') {
        Navigator.of(context).pop();
        return;
      }
      await _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// Kailangan ng larawan at (kung COD) ng cash bago ma-mark na delivered
  Future<void> _confirmDelivered(Map<String, dynamic> o) async {
    final done = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => RiderProofScreen(deliveryId: widget.id, order: o)),
    );
    if (done == true) {
      await LocationService.I.stop();
      if (mounted) Navigator.of(context).pop();
    }
  }

  Future<void> _openUrl(String url) async {
    final ok = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    if (!ok && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Hindi mabuksan.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final d = _d;
    return Scaffold(
      appBar: AppBar(backgroundColor: riderGreen, title: Text(d == null ? 'Delivery' : (d['order'] as Map)['order_number'] as String)),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : d == null
              ? Center(child: Text(_error ?? 'May error.'))
              : _body(d),
    );
  }

  Widget _body(Map<String, dynamic> d) {
    final o = Map<String, dynamic>.from(d['order'] as Map);
    final c = Map<String, dynamic>.from(d['customer'] as Map);
    final shopM = Map<String, dynamic>.from(d['shop'] as Map);
    final status = d['status'] as String;

    final custLat = _n(o['lat']);
    final custLng = _n(o['lng']);
    final cust = (custLat != null && custLng != null) ? LatLng(custLat, custLng) : null;
    final shop = LatLng(_n(shopM['lat']) ?? 0, _n(shopM['lng']) ?? 0);
    final items = (o['items'] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
    final cod = o['payment_method'] == 'cod' && o['payment_status'] == 'unpaid';

    return ListView(padding: const EdgeInsets.all(14), children: [
      // MAPA
      SizedBox(
        height: 240,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: FlutterMap(
            options: MapOptions(initialCenter: cust ?? shop, initialZoom: 14),
            children: [
              TileLayer(
                urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                userAgentPackageName: 'com.kjmcoffeebrew.app',
              ),
              ValueListenableBuilder<Position?>(
                valueListenable: LocationService.I.position,
                builder: (_, pos, __) => MarkerLayer(markers: [
                  Marker(point: shop, width: 40, height: 40, child: const Text('â˜•', style: TextStyle(fontSize: 28))),
                  if (cust != null) Marker(point: cust, width: 40, height: 40, child: const Text('ðŸ ', style: TextStyle(fontSize: 28))),
                  if (pos != null) Marker(point: LatLng(pos.latitude, pos.longitude), width: 40, height: 40, child: const Text('ðŸ›µ', style: TextStyle(fontSize: 28))),
                ]),
              ),
            ],
          ),
        ),
      ),
      const SizedBox(height: 8),

      // GPS status
      ValueListenableBuilder<bool>(
        valueListenable: LocationService.I.tracking,
        builder: (_, on, __) => ValueListenableBuilder<String?>(
          valueListenable: LocationService.I.error,
          builder: (_, err, __) => Text(
            err != null ? 'âš ï¸ $err' : (on ? 'ðŸ“¡ Nagpapadala ng GPS tuwing 5 segundo' : 'GPS: hindi pa nagsisimula'),
            style: TextStyle(color: err != null ? Colors.red : (on ? success : Colors.black54), fontSize: 13),
          ),
        ),
      ),
      const SizedBox(height: 12),

      // IMPORMASYON
      Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text(deliveryStatusLabel(status), style: TextStyle(color: deliveryStatusColor(status), fontWeight: FontWeight.w600)),
              if (d['distance_km'] != null) Text('${d['distance_km']} km'),
            ]),
            const Divider(),
            Text('ðŸ‘¤ ${c['name'] ?? 'â€”'}', style: const TextStyle(fontWeight: FontWeight.w600)),
            if (c['phone'] != null)
              TextButton.icon(
                style: TextButton.styleFrom(padding: EdgeInsets.zero, alignment: Alignment.centerLeft),
                onPressed: () => _openUrl('tel:${c['phone']}'),
                icon: const Icon(Icons.call, size: 18),
                label: Text('${c['phone']}'),
              ),
            Text('ðŸ“ ${o['address'] ?? 'â€”'}'),
            if ((o['notes'] ?? '').toString().isNotEmpty) Text('ðŸ“ ${o['notes']}'),
          ]),
        ),
      ),
      Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Mga Item', style: TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            for (final it in items)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: Text('${it['qty']}Ã— ${it['name']}${(it['options'] as List).isEmpty ? '' : '  (${(it['options'] as List).join(', ')})'}'),
              ),
            const Divider(),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text(cod ? 'Kolektahin (COD)' : 'Bayad: ${(o['payment_status'] as String).toUpperCase()}'),
              Text('â‚±${(o['total'] as num).toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w700, color: caramel, fontSize: 18)),
            ]),
          ]),
        ),
      ),
      const SizedBox(height: 8),

      // NAVIGATE (bubukas ng Google Maps app, walang API key)
      if (cust != null && ['accepted', 'picked_up', 'on_the_way'].contains(status))
        OutlinedButton.icon(
          style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
          onPressed: () => _openUrl('https://www.google.com/maps/dir/?api=1&destination=${cust.latitude},${cust.longitude}&travelmode=driving'),
          icon: const Icon(Icons.navigation),
          label: const Text('Mag-navigate (Google Maps)'),
        ),
      const SizedBox(height: 8),

      // MGA AKSYON
      if (_busy)
        const Center(child: Padding(padding: EdgeInsets.all(12), child: CircularProgressIndicator()))
      else ...[
        if (status == 'assigned') ...[
          FilledButton(onPressed: () => _act('accept'), child: const Text('Tanggapin ang delivery')),
          const SizedBox(height: 8),
          OutlinedButton(
            style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48), foregroundColor: Colors.red),
            onPressed: () => _act('decline'),
            child: const Text('Tanggihan'),
          ),
        ],
        if (status == 'accepted') FilledButton(onPressed: () => _act('pickup'), child: const Text('Kinuha ko na ang order')),
        if (status == 'picked_up') FilledButton(onPressed: () => _act('start'), child: const Text('Simulan ang Delivery (start GPS)')),
        if (status == 'on_the_way')
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: success),
            onPressed: () => _confirmDelivered(o),
            child: const Text('Mark as Delivered'),
          ),
      ],
      const SizedBox(height: 24),
    ]);
  }
}

