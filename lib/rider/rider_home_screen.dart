import 'dart:async';

import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/theme.dart';
import 'rider_location_service.dart';
import 'rider_theme.dart';
import '../core/util.dart';
import 'rider_delivery_screen.dart';
import '../screens/auth_screen.dart';

List<Map<String, dynamic>> _asList(dynamic v) =>
    (v as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();

class RiderHomeScreen extends StatefulWidget {
  const RiderHomeScreen({super.key});

  @override
  State<RiderHomeScreen> createState() => _RiderHomeScreenState();
}

class _RiderHomeScreenState extends State<RiderHomeScreen> {
  List<Map<String, dynamic>> _active = [];
  List<Map<String, dynamic>> _history = [];
  Map<String, dynamic> _summary = {};
  bool _available = false;
  bool _loading = true;
  String? _error;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    _load();
    _poll = Timer.periodic(
      const Duration(seconds: 15),
      (_) => _load(silent: true),
    );
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent) setState(() => _loading = true);
    try {
      final a = await Api.I.deliveries();
      final h = await Api.I.history();
      if (!mounted) return;

      final active = _asList(a['items']);
      setState(() {
        _active = active;
        _history = _asList(h['items']);
        _summary = Map<String, dynamic>.from((h['summary'] ?? {}) as Map);
        _available = a['is_available'] == true;
        _error = null;
        _loading = false;
      });

      // Start or stop GPS depending on whether there is an "on_the_way" delivery
      if (active.any((d) => d['status'] == 'on_the_way')) {
        LocationService.I.start(
          deliveryId:
              active.firstWhere((d) => d['status'] == 'on_the_way')['id']
                  as int,
        );
      } else {
        LocationService.I.stop();
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
      if (!Api.I.loggedIn) _goLogin();
    }
  }

  void _goLogin() {
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const AuthScreen()),
      (_) => false,
    );
  }

  Future<void> _logout() async {
    await LocationService.I.stop();
    await Api.I.logout();
    if (mounted) _goLogin();
  }

  Future<void> _toggleAvailable(bool v) async {
    try {
      final r = await Api.I.setAvailability(v);
      if (mounted) setState(() => _available = r);
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _open(int id) async {
    await Navigator.of(context)
        .push(MaterialPageRoute(builder: (_) => RiderDeliveryScreen(id: id)));
    _load(silent: true);
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          backgroundColor: riderGreen,
          title: const Text('🛵 Rider · Deliveries'),
          actions: [
            Row(
              children: [
                Text(
                  _available ? 'Online' : 'Offline',
                  style: const TextStyle(fontSize: 12),
                ),
                Switch(
                  value: _available,
                  activeThumbColor: success,
                  onChanged: _toggleAvailable,
                ),
              ],
            ),
            IconButton(icon: const Icon(Icons.logout), onPressed: _logout),
          ],
          bottom: const TabBar(
            labelColor: cream,
            unselectedLabelColor: Colors.white54,
            indicatorColor: caramel,
            tabs: [
              Tab(text: 'Active'),
              Tab(text: 'History'),
            ],
          ),
        ),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : Column(
                children: [
                  if (_error != null)
                    Container(
                      width: double.infinity,
                      color: Colors.red.shade50,
                      padding: const EdgeInsets.all(10),
                      child: Text(
                        _error!,
                        style: const TextStyle(color: Colors.red),
                      ),
                    ),
                  ValueListenableBuilder<bool>(
                    valueListenable: LocationService.I.tracking,
                    builder: (_, on, _) => on
                        ? Container(
                            width: double.infinity,
                            color: success,
                            padding: const EdgeInsets.symmetric(vertical: 6),
                            child: const Text(
                              '📡 Sending GPS',
                              textAlign: TextAlign.center,
                              style: TextStyle(color: Colors.white),
                            ),
                          )
                        : const SizedBox.shrink(),
                  ),
                  if (dbl(_summary['cash_on_hand']) > 0)
                    Container(
                      width: double.infinity,
                      color: Colors.amber.shade100,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 10,
                      ),
                      child: Text(
                        '💵 Cash on hand: ${peso(dbl(_summary['cash_on_hand']))}\nHand it over to shop staff to confirm deliveries.',
                        style: const TextStyle(
                          fontWeight: FontWeight.w600,
                          height: 1.35,
                        ),
                      ),
                    ),
                  Expanded(
                    child: TabBarView(
                      children: [
                        _list(
                          _active,
                          'No active deliveries.\nWaiting for staff assignment.',
                        ),
                        Column(
                          children: [
                            Padding(
                              padding: const EdgeInsets.all(12),
                              child: Row(
                                children: [
                                  _stat('Today', '${_summary['today'] ?? 0}'),
                                  _stat(
                                    'Total',
                                    '${_summary['total_deliveries'] ?? 0}',
                                  ),
                                  _stat('Km', '${_summary['total_km'] ?? 0}'),
                                ],
                              ),
                            ),
                            Expanded(
                              child: _list(
                                _history,
                                'No completed deliveries yet.',
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
      ),
    );
  }

  Widget _stat(String label, String value) => Expanded(
    child: Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Column(
          children: [
            Text(
              value,
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w600),
            ),
            Text(
              label,
              style: const TextStyle(fontSize: 12, color: Colors.black54),
            ),
          ],
        ),
      ),
    ),
  );

  Widget _list(List<Map<String, dynamic>> items, String empty) {
    return RefreshIndicator(
      onRefresh: _load,
      child: items.isEmpty
          ? ListView(
              children: [
                const SizedBox(height: 120),
                Center(
                  child: Text(
                    empty,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Colors.black54),
                  ),
                ),
              ],
            )
          : ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: items.length,
              itemBuilder: (_, i) => _card(items[i]),
            ),
    );
  }

  Widget _card(Map<String, dynamic> d) {
    final o = Map<String, dynamic>.from(d['order'] as Map);
    final c = Map<String, dynamic>.from(d['customer'] as Map);
    final status = d['status'] as String;
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => _open(d['id'] as int),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    o['order_number'] as String,
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: deliveryStatusColor(status),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      deliveryStatusLabel(status),
                      style: const TextStyle(color: Colors.white, fontSize: 11),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text('👤 ${c['name'] ?? '—'}'),
              Text(
                '📍 ${o['address'] ?? '—'}',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 6),
              Text(
                '₱${(o['total'] as num).toStringAsFixed(2)} · ${(o['payment_method'] as String).toUpperCase()}',
                style: const TextStyle(
                  color: caramel,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
