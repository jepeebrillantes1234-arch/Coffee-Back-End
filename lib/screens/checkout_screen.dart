import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';

import '../core/api.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../state/cart.dart';
import '../state/promo_state.dart';
import '../state/app_state.dart';
import 'order_detail_screen.dart';
import 'payment_screen.dart';
import 'root_screen.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  String _type = 'delivery';
  LatLng? _pin;
  final _address = TextEditingController();
  final _notes = TextEditingController();
  final _promo = TextEditingController();
  final _map = MapController();

  List<Map<String, dynamic>> _saved = [];
  bool _saveAddress = false;

  Map<String, dynamic>? _quote;
  String? _error;
  bool _quoting = false;
  bool _placing = false;
  bool _locating = false;
  int _quoteSeq = 0;

  static const _defaultCenter = LatLng(14.5995, 120.9842);

  @override
  void initState() {
    super.initState();
    _promo.text = SelectedPromo.I.code ?? '';   // awtomatikong code mula sa Home
    _refresh();
    _loadSaved();
  }

  @override
  void dispose() {
    _address.dispose();
    _notes.dispose();
    _promo.dispose();
    super.dispose();
  }

  Future<void> _loadSaved() async {
    try {
      final a = await Api.I.addresses();
      if (mounted) setState(() => _saved = a);
    } catch (_) {}
  }

  void _useSaved(Map<String, dynamic> a) {
    final ll = LatLng(dbl(a['latitude']), dbl(a['longitude']));
    setState(() {
      _pin = ll;
      _address.text = '${a['full_address']}';
      _saveAddress = false;
    });
    try {
      _map.move(ll, 16);
    } catch (_) {}
    _refresh();
  }

  Map<String, dynamic> _payload({bool forOrder = false}) => {
        'order_type': _type,
        'items': Cart.I.lines.map((l) => l.toPayload()).toList(),
        if (_promo.text.trim().isNotEmpty) 'promo_code': _promo.text.trim(),
        if (_type == 'delivery' && _pin != null) 'delivery_lat': _pin!.latitude,
        if (_type == 'delivery' && _pin != null) 'delivery_lng': _pin!.longitude,
        // The preview endpoint validates delivery_address too, so send it
        // during both quote refreshes and final order creation.
        if (_type == 'delivery' && _pin != null) ...{
          'delivery_address': _address.text.trim().isEmpty
              ? 'GPS ${_pin!.latitude.toStringAsFixed(6)}, ${_pin!.longitude.toStringAsFixed(6)}'
              : _address.text.trim(),
          'address': _address.text.trim().isEmpty
              ? 'GPS ${_pin!.latitude.toStringAsFixed(6)}, ${_pin!.longitude.toStringAsFixed(6)}'
              : _address.text.trim(),
        },
        if (forOrder && _notes.text.trim().isNotEmpty) 'notes': _notes.text.trim(),
      };

  Future<void> _refresh() async {
    if (_type == 'delivery' && _pin == null) {
      setState(() {
        _quote = null;
        _error = null;
      });
      return;
    }
    final seq = ++_quoteSeq;
    setState(() => _quoting = true);
    try {
      final q = await Api.I.preview(_payload());
      if (!mounted || seq != _quoteSeq) return;
      setState(() {
        _quote = q;
        _error = null;
      });
    } on ApiException catch (e) {
      if (!mounted || seq != _quoteSeq) return;
      setState(() {
        _quote = null;
        _error = e.message;
      });
    } finally {
      if (mounted && seq == _quoteSeq) setState(() => _quoting = false);
    }
  }

  Future<void> _useGps() async {
    setState(() => _locating = true);
    try {
      if (!await Geolocator.isLocationServiceEnabled()) throw 'I-on ang Location/GPS ng phone.';
      var perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) perm = await Geolocator.requestPermission();
      if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) {
        throw 'Kailangan ng Location permission.';
      }
      final p = await Geolocator.getCurrentPosition();
      final ll = LatLng(p.latitude, p.longitude);
      setState(() {
        _pin = ll;
        if (_address.text.trim().isEmpty) {
          _address.text = 'GPS ${p.latitude.toStringAsFixed(6)}, ${p.longitude.toStringAsFixed(6)}';
        }
      });
      try {
        _map.move(ll, 16);
      } catch (_) {}
      _refresh();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  Future<void> _place() async {
    setState(() => _placing = true);
    final nav = Navigator.of(context);
    try {
      final payload = _payload(forOrder: true);
      final order = await Api.I.placeOrder(payload);

      if (_type == 'delivery' && _saveAddress && _pin != null) {
        try {
          await Api.I.saveAddress('Home', payload['delivery_address'] as String, _pin!.latitude, _pin!.longitude);
        } catch (_) {}
      }

      Cart.I.clear();
      SelectedPromo.I.clear();
      rootTab.value = 3;
      nav.popUntil((r) => r.isFirst);
      nav.push(MaterialPageRoute(
        builder: (_) => order['status'] == 'awaiting_payment' ? PaymentScreen(order: order) : OrderDetailScreen(id: order['id'] as int),
      ));
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _error = e.message);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _placing = false);
    }
  }

  bool get _canPlace => _quote != null && !_quoting && !_placing &&
      (_type == 'pickup' || (_pin != null && _address.text.trim().isNotEmpty));

  @override
  Widget build(BuildContext context) {
    final q = _quote;
    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: Column(children: [
        Expanded(
          child: ListView(padding: const EdgeInsets.all(14), children: [
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'delivery', label: Text('Delivery'), icon: Icon(Icons.delivery_dining)),
                ButtonSegment(value: 'pickup', label: Text('Pickup'), icon: Icon(Icons.storefront)),
              ],
              selected: {_type},
              onSelectionChanged: (s) {
                setState(() => _type = s.first);
                _refresh();
              },
            ),
            const SizedBox(height: 14),

            if (_type == 'delivery') ...[
              const Text('ðŸ“ Saan ihahatid?', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
              if (_saved.isNotEmpty) ...[
                const SizedBox(height: 8),
                SizedBox(
                  height: 40,
                  child: ListView(scrollDirection: Axis.horizontal, children: [
                    for (final a in _saved)
                      Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ActionChip(
                          avatar: const Icon(Icons.bookmark_outline, size: 18),
                          label: Text('${a['label'] ?? 'Home'}'),
                          onPressed: () => _useSaved(a),
                        ),
                      ),
                  ]),
                ),
              ],
              const SizedBox(height: 4),
              const Text('I-tap ang mapa para ilagay ang pin, o gamitin ang GPS mo.', style: TextStyle(fontSize: 12, color: Colors.black54)),
              const SizedBox(height: 8),
              SizedBox(
                height: 260,
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(16),
                  child: FlutterMap(
                    mapController: _map,
                    options: MapOptions(
                      initialCenter: _pin ?? _defaultCenter,
                      initialZoom: _pin == null ? 12 : 16,
                      onTap: (_, point) {
                        setState(() {
                          _pin = point;
                          if (_address.text.trim().isEmpty) {
                            _address.text = 'GPS ${point.latitude.toStringAsFixed(6)}, ${point.longitude.toStringAsFixed(6)}';
                          }
                        });
                        _refresh();
                      },
                    ),
                    children: [
                      TileLayer(urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', userAgentPackageName: 'com.kjmcoffeebrew.app'),
                      if (_pin != null)
                        MarkerLayer(markers: [
                          Marker(
                            point: _pin!,
                            width: 40,
                            height: 40,
                            child: Container(
                              decoration: BoxDecoration(
                                color: Colors.red,
                                shape: BoxShape.circle,
                                border: Border.all(color: Colors.white, width: 3),
                                boxShadow: const [BoxShadow(color: Colors.black38, blurRadius: 6)],
                              ),
                            ),
                          ),
                        ]),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: _locating ? null : _useGps,
                icon: _locating ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.my_location),
                label: const Text('Gamitin ang GPS ko'),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _address,
                onChanged: (_) => _refresh(),
                decoration: const InputDecoration(
                  labelText: 'Address / landmark',
                  hintText: 'Hal. Blk 5 Lot 2, tabi ng 7-Eleven',
                  border: OutlineInputBorder(),
                  filled: true,
                  fillColor: Colors.white,
                ),
              ),
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                value: _saveAddress,
                onChanged: _pin == null ? null : (v) => setState(() => _saveAddress = v ?? false),
                title: const Text('I-save ang address na ito', style: TextStyle(fontSize: 14)),
              ),
            ] else
              Card(child: Padding(padding: const EdgeInsets.all(14), child: Text(AppState.online ? 'ðŸª Kukunin mo ang order sa shop. Babayaran ito online sa GCash bago ihanda.' : 'ðŸª Kukunin mo ang order sa shop. Cash ang bayad sa counter.'))),

            TextField(
              controller: _notes,
              decoration: const InputDecoration(labelText: 'Note sa order (opsyonal)', border: OutlineInputBorder(), filled: true, fillColor: Colors.white),
            ),
            const SizedBox(height: 10),
            Row(children: [
              Expanded(
                child: TextField(
                  controller: _promo,
                  textCapitalization: TextCapitalization.characters,
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    labelText: 'Promo code',
                    border: const OutlineInputBorder(),
                    filled: true,
                    fillColor: Colors.white,
                    suffixIcon: _promo.text.isEmpty
                        ? null
                        : IconButton(
                            icon: const Icon(Icons.close),
                            onPressed: () {
                              _promo.clear();
                              SelectedPromo.I.clear();
                              setState(() {});
                              _refresh();
                            },
                          ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 56)), onPressed: _refresh, child: const Text('Apply')),
            ]),
            const SizedBox(height: 16),

            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: _quoting
                    ? const Center(child: Padding(padding: EdgeInsets.all(8), child: CircularProgressIndicator()))
                    : q == null
                        ? Text(_error ?? (_type == 'delivery' ? 'Maglagay ng pin sa mapa para makita ang total.' : '...'),
                            style: TextStyle(color: _error != null ? Colors.red : Colors.black54))
                        : Column(children: [
                            for (final it in (q['items'] as List))
                              Padding(
                                padding: const EdgeInsets.symmetric(vertical: 2),
                                child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                                  Expanded(child: Text('${it['qty']}Ã— ${it['name']}')),
                                  Text(peso(dbl(it['line_total']))),
                                ]),
                              ),
                            const Divider(),
                            _row('Subtotal', peso(dbl(q['subtotal']))),
                            if (dbl(q['discount']) > 0) _row('Diskwento (${q['discount_label']})', '-${peso(dbl(q['discount']))}', color: success),
                            if (_type == 'delivery') _row('Delivery fee (${q['distance_km']} km)', peso(dbl(q['delivery_fee']))),
                            const Divider(),
                            _row('Total', peso(dbl(q['total'])), bold: true),
                            const SizedBox(height: 4),
                            Text(AppState.online ? 'Bayad: GCash (online).' : (_type == 'delivery' ? 'Bayad: Cash on Delivery. Ibabayad mo sa rider pagdating.' : 'Bayad: Cash sa shop.'), style: const TextStyle(fontSize: 12, color: Colors.black54)),
                          ]),
              ),
            ),
            if (_error != null && q != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_error!, style: const TextStyle(color: Colors.red))),
          ]),
        ),
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: FilledButton(
              onPressed: _canPlace ? _place : null,
              child: _placing
                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Text(AppState.online ? (q == null ? 'Magbayad sa GCash' : 'Magbayad sa GCash Â· ${peso(dbl(q['total']))}') : (q == null ? 'Place Order' : 'Place Order Â· ${peso(dbl(q['total']))}')),
            ),
          ),
        ),
      ]),
    );
  }

  Widget _row(String l, String v, {bool bold = false, Color? color}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text(l, style: TextStyle(fontWeight: bold ? FontWeight.w700 : null, fontSize: bold ? 17 : null, color: color)),
          Text(v, style: TextStyle(fontWeight: bold ? FontWeight.w700 : null, fontSize: bold ? 17 : null, color: color ?? (bold ? caramel : null))),
        ]),
      );
}

