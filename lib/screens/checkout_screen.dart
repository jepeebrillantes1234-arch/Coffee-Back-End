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
    _promo.text = SelectedPromo.I.code ?? ''; // awtomatikong code mula sa Home
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

  Future<void> _selectDeliveryAddress() async {
    if (_locating) return;
    final selected = await _useGps();
    if (!selected && mounted) await _openAddressPicker();
  }

  Future<void> _openAddressPicker() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, modalSetState) => SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              16,
              0,
              16,
              16 + MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: SizedBox(
              height: MediaQuery.sizeOf(context).height * .72,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Delivery Address',
                    style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Choose a saved address or tap the map to set your location.',
                    style: TextStyle(fontSize: 12, color: Colors.black54),
                  ),
                  if (_saved.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    SizedBox(
                      height: 38,
                      child: ListView(
                        scrollDirection: Axis.horizontal,
                        children: [
                          for (final saved in _saved)
                            Padding(
                              padding: const EdgeInsets.only(right: 8),
                              child: ActionChip(
                                avatar: const Icon(
                                  Icons.bookmark_outline,
                                  size: 17,
                                ),
                                label: Text(
                                  '${saved['label'] ?? 'Saved address'}',
                                ),
                                onPressed: () {
                                  _useSaved(saved);
                                  Navigator.pop(sheetContext);
                                },
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 10),
                  Expanded(
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
                                _address.text =
                                    'GPS ${point.latitude.toStringAsFixed(6)}, ${point.longitude.toStringAsFixed(6)}';
                              }
                            });
                            modalSetState(() {});
                            _refresh();
                          },
                        ),
                        children: [
                          TileLayer(
                            urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                            userAgentPackageName: 'com.kjmcoffeebrew.app',
                          ),
                          if (_pin != null)
                            MarkerLayer(
                              markers: [
                                Marker(
                                  point: _pin!,
                                  width: 40,
                                  height: 40,
                                  child: Container(
                                    decoration: BoxDecoration(
                                      color: Colors.red,
                                      shape: BoxShape.circle,
                                      border: Border.all(
                                        color: Colors.white,
                                        width: 3,
                                      ),
                                      boxShadow: const [
                                        BoxShadow(
                                          color: Colors.black38,
                                          blurRadius: 6,
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ],
                            ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _address,
                    onChanged: (_) {
                      modalSetState(() {});
                      _refresh();
                    },
                    decoration: const InputDecoration(
                      labelText: 'Street, barangay, city, or landmark',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.location_on_outlined),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _locating
                              ? null
                              : () async {
                                  await _useGps();
                                  modalSetState(() {});
                                },
                          icon: _locating
                              ? const SizedBox(
                                  width: 16,
                                  height: 16,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.my_location),
                          label: const Text('Use my GPS'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      IconButton(
                        tooltip: _saveAddress
                            ? 'Address will be saved'
                            : 'Save this address',
                        onPressed: _pin == null
                            ? null
                            : () {
                                setState(() => _saveAddress = !_saveAddress);
                                modalSetState(() {});
                              },
                        icon: Icon(
                          _saveAddress
                              ? Icons.bookmark_added
                              : Icons.bookmark_add_outlined,
                          color: _saveAddress ? espresso : null,
                        ),
                      ),
                      FilledButton(
                        onPressed: _pin == null || _address.text.trim().isEmpty
                            ? null
                            : () => Navigator.pop(sheetContext),
                        child: const Text('Done'),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<bool> _useGps() async {
    if (!mounted) return false;
    setState(() => _locating = true);
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        throw 'Turn on your phone location to select your address automatically.';
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        throw 'Allow location permission to use your current location.';
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 12),
        ),
      );
      final point = LatLng(position.latitude, position.longitude);
      if (!mounted) return false;
      setState(() {
        _pin = point;
        if (_address.text.trim().isEmpty || _address.text.startsWith('GPS ')) {
          _address.text =
              'Current location (${position.latitude.toStringAsFixed(6)}, ${position.longitude.toStringAsFixed(6)})';
        }
        _error = null;
      });
      try {
        _map.move(point, 16);
      } catch (_) {}
      _refresh();
      return true;
    } catch (error) {
      if (mounted) {
        final message =
            error is Exception && error.toString().contains('time limit')
            ? 'Could not get your location in time. Choose your address on the map instead.'
            : '$error';
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(message)));
      }
      return false;
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
          await Api.I.saveAddress(
            'Home',
            payload['delivery_address'] as String,
            _pin!.latitude,
            _pin!.longitude,
          );
        } catch (_) {}
      }

      Cart.I.clear();
      SelectedPromo.I.clear();
      rootTab.value = 3;
      nav.popUntil((r) => r.isFirst);
      nav.push(
        MaterialPageRoute(
          builder: (_) => order['status'] == 'awaiting_payment'
              ? PaymentScreen(order: order)
              : OrderDetailScreen(id: order['id'] as int),
        ),
      );
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _error = e.message);
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _placing = false);
    }
  }

  bool get _canPlace =>
      _quote != null &&
      !_quoting &&
      !_placing &&
      (_type == 'pickup' || (_pin != null && _address.text.trim().isNotEmpty));

  Widget _sectionCard({required Widget child}) => Container(
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: const Color(0xFFECE7E2)),
    ),
    child: child,
  );

  Widget _sectionTitle(String title) => Padding(
    padding: const EdgeInsets.only(bottom: 9),
    child: Text(
      title,
      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final quote = _quote;
    final items = quote?['items'] is List
        ? (quote!['items'] as List)
        : const [];
    final address = _address.text.trim();
    final displayedSubtotal = quote == null
        ? Cart.I.subtotal
        : dbl(quote['subtotal']);
    final displayedTotal = quote == null ? null : dbl(quote['total']);
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text(
          'Checkout',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 18),
              children: [
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(
                      value: 'delivery',
                      label: Text('Delivery'),
                      icon: Icon(Icons.delivery_dining_rounded),
                    ),
                    ButtonSegment(
                      value: 'pickup',
                      label: Text('Pickup'),
                      icon: Icon(Icons.storefront_rounded),
                    ),
                  ],
                  selected: {_type},
                  onSelectionChanged: (selection) {
                    setState(() => _type = selection.first);
                    _refresh();
                  },
                ),
                const SizedBox(height: 20),
                if (_type == 'delivery') ...[
                  _sectionTitle('Delivery Address'),
                  _sectionCard(
                    child: ListTile(
                      onTap: _selectDeliveryAddress,
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 13,
                        vertical: 4,
                      ),
                      leading: const Icon(
                        Icons.location_on_outlined,
                        color: espresso,
                      ),
                      title: Text(
                        address.isEmpty ? 'Choose a delivery address' : address,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      subtitle: Text(
                        _pin == null
                            ? 'Required for delivery'
                            : '${_pin!.latitude.toStringAsFixed(5)}, ${_pin!.longitude.toStringAsFixed(5)}',
                        style: const TextStyle(
                          fontSize: 11,
                          color: Colors.black54,
                        ),
                      ),
                      trailing: _locating
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.chevron_right_rounded),
                    ),
                  ),
                  const SizedBox(height: 17),
                ],
                _sectionTitle('Payment Method'),
                _sectionCard(
                  child: Column(
                    children: [
                      ListTile(
                        dense: true,
                        leading: const Icon(
                          Icons.payments_outlined,
                          color: espresso,
                        ),
                        title: Text(
                          _type == 'delivery'
                              ? 'Cash on Delivery'
                              : 'Cash at Pickup',
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        subtitle: Text(
                          _type == 'delivery'
                              ? 'Pay the rider when your order arrives.'
                              : 'Pay at the shop counter.',
                          style: const TextStyle(fontSize: 11),
                        ),
                        trailing: Icon(
                          AppState.online
                              ? Icons.radio_button_unchecked
                              : Icons.check_circle,
                          color: AppState.online ? Colors.black38 : espresso,
                          size: 19,
                        ),
                      ),
                      const Divider(height: 1, indent: 54),
                      ListTile(
                        dense: true,
                        leading: const Icon(
                          Icons.credit_card_outlined,
                          color: Colors.black45,
                        ),
                        title: const Text(
                          'Online Payment',
                          style: TextStyle(fontSize: 13, color: Colors.black54),
                        ),
                        subtitle: Text(
                          AppState.online
                              ? 'GCash payment is enabled.'
                              : 'Coming soon',
                          style: const TextStyle(fontSize: 11),
                        ),
                        trailing: AppState.online
                            ? const Icon(
                                Icons.check_circle,
                                color: espresso,
                                size: 19,
                              )
                            : const Icon(
                                Icons.lock_outline,
                                color: Colors.black38,
                                size: 18,
                              ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 17),
                _sectionTitle('Order Summary'),
                _sectionCard(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      children: [
                        if (_quoting)
                          const Padding(
                            padding: EdgeInsets.symmetric(vertical: 8),
                            child: LinearProgressIndicator(minHeight: 2),
                          ),
                        if (quote == null && !_quoting)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: Text(
                              _error ??
                                  (_type == 'delivery'
                                      ? 'Select a delivery address to calculate the total.'
                                      : 'Unable to calculate the order total.'),
                              style: TextStyle(
                                fontSize: 12,
                                color: _error == null
                                    ? Colors.black54
                                    : Colors.red,
                              ),
                            ),
                          ),
                        for (final item in items)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 3),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    '${item['qty']} ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â ${item['name']}',
                                    style: const TextStyle(fontSize: 12),
                                  ),
                                ),
                                Text(
                                  peso(dbl(item['line_total'])),
                                  style: const TextStyle(fontSize: 12),
                                ),
                              ],
                            ),
                          ),
                        if (items.isNotEmpty) const Divider(height: 18),
                        _row('Subtotal', peso(displayedSubtotal)),
                        if (quote != null && dbl(quote['discount']) > 0)
                          _row(
                            'Discount${quote['discount_label'] == null ? '' : ' (${quote['discount_label']})'}',
                            '-${peso(dbl(quote['discount']))}',
                            color: success,
                          ),
                        if (_type == 'delivery')
                          _row(
                            'Delivery Fee',
                            quote == null
                                ? 'Calculated after address'
                                : peso(dbl(quote['delivery_fee'])),
                          ),
                        const Divider(height: 20),
                        _row(
                          'Total',
                          displayedTotal == null
                              ? 'Calculated at checkout'
                              : peso(displayedTotal),
                          bold: true,
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                ExpansionTile(
                  tilePadding: EdgeInsets.zero,
                  childrenPadding: const EdgeInsets.only(bottom: 12),
                  title: const Text(
                    'Add a note or promo code',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                  children: [
                    TextField(
                      controller: _notes,
                      decoration: const InputDecoration(
                        labelText: 'Order note (optional)',
                        border: OutlineInputBorder(),
                        prefixIcon: Icon(Icons.edit_note_rounded),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _promo,
                            textCapitalization: TextCapitalization.characters,
                            onChanged: (_) => setState(() {}),
                            decoration: InputDecoration(
                              labelText: 'Promo code',
                              border: const OutlineInputBorder(),
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
                        const SizedBox(width: 9),
                        OutlinedButton(
                          onPressed: _quoting ? null : _refresh,
                          child: const Text('Apply'),
                        ),
                      ],
                    ),
                  ],
                ),
                if (_error != null && quote != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text(
                      _error!,
                      style: const TextStyle(color: Colors.red, fontSize: 12),
                    ),
                  ),
              ],
            ),
          ),
          SafeArea(
            top: false,
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(top: BorderSide(color: Color(0xFFECE7E2))),
              ),
              child: SizedBox(
                height: 48,
                child: FilledButton(
                  onPressed: _canPlace ? _place : null,
                  style: FilledButton.styleFrom(
                    backgroundColor: espresso,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: _placing
                      ? const SizedBox(
                          width: 21,
                          height: 21,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : Text(
                          AppState.online
                              ? (quote == null
                                    ? 'Continue to Payment'
                                    : 'Continue to Payment ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â· ${peso(dbl(quote['total']))}')
                              : (quote == null
                                    ? 'Place Order'
                                    : 'Place Order ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â· ${peso(dbl(quote['total']))}'),
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _row(String l, String v, {bool bold = false, Color? color}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 2),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          l,
          style: TextStyle(
            fontWeight: bold ? FontWeight.w700 : null,
            fontSize: bold ? 17 : null,
            color: color,
          ),
        ),
        Text(
          v,
          style: TextStyle(
            fontWeight: bold ? FontWeight.w700 : null,
            fontSize: bold ? 17 : null,
            color: color ?? (bold ? caramel : null),
          ),
        ),
      ],
    ),
  );
}
