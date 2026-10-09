import 'dart:async';

import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/theme.dart';
import '../core/util.dart';
import 'order_detail_screen.dart';
import 'root_screen.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  List<Map<String, dynamic>> _orders = [];
  bool _loading = true;
  String? _error;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    rootTab.addListener(_onTab);
    _poll = Timer.periodic(const Duration(seconds: 15), (_) {
      if (rootTab.value == 3) _load(silent: true);
    });
  }

  void _onTab() {
    if (rootTab.value == 3) _load();
  }

  @override
  void dispose() {
    rootTab.removeListener(_onTab);
    _poll?.cancel();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent && mounted) setState(() => _loading = true);
    try {
      final o = await Api.I.orders();
      if (!mounted) return;
      setState(() {
        _orders = o;
        _error = null;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Orders')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: _orders.isEmpty
                  ? ListView(children: [
                      const SizedBox(height: 120),
                      Center(
                        child: Text(
                          _error ?? 'You have no orders yet.',
                          style: TextStyle(
                            color: _error != null ? Colors.red : Colors.black54,
                          ),
                        ),
                      ),
                    ])
                  : ListView.builder(
                      padding: const EdgeInsets.all(12),
                      itemCount: _orders.length,
                      itemBuilder: (_, i) {
                        final o = _orders[i];
                        final status = '${o['status']}';
                        return Card(
                          child: ListTile(
                            onTap: () async {
                              await Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) =>
                                      OrderDetailScreen(id: o['id'] as int),
                                ),
                              );
                              _load(silent: true);
                            },
                            title: Row(children: [
                              Expanded(
                                child: Text(
                                  '${o['order_number']}',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w600),
                                ),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(
                                  color: statusColor(status),
                                  borderRadius: BorderRadius.circular(20),
                                ),
                                child: Text(
                                  statusLabel(status, '${o['order_type']}'),
                                  style: const TextStyle(
                                      color: Colors.white, fontSize: 10),
                                ),
                              ),
                            ]),
                            subtitle: Text(
                              '${(o['order_type'] == 'delivery') ? 'Delivery' : 'Pickup'} · ${dateTimeText(o['created_at'])}\n${peso(dbl(o['total']))}',
                              style: const TextStyle(height: 1.4),
                            ),
                            isThreeLine: true,
                          ),
                        );
                      },
                    ),
            ),
    );
  }
}