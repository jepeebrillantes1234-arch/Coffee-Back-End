import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/theme.dart';
import '../core/util.dart';
import 'order_detail_screen.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final r = await Api.I.notifications();
      if (!mounted) return;
      setState(() {
        _items = mapList(r['items']);
        _loading = false;
        _error = null;
      });
      if (((r['unread'] as num?) ?? 0) > 0) {
        await Api.I.readAllNotifications(); // minarkahang nabasa na pagkabukas
      }
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
      appBar: AppBar(title: const Text('Mga Notification')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: _items.isEmpty
                  ? ListView(children: [
                      const SizedBox(height: 120),
                      Center(child: Text(_error ?? 'Wala pang notification.', style: const TextStyle(color: Colors.black54))),
                    ])
                  : ListView.separated(
                      itemCount: _items.length,
                      separatorBuilder: (_, __) => const Divider(height: 1),
                      itemBuilder: (_, i) {
                        final n = _items[i];
                        final orderId = (n['data'] is Map) ? (n['data']['order_id'] as num?)?.toInt() : null;
                        return ListTile(
                          tileColor: n['is_read'] == true ? null : Colors.white,
                          leading: Icon(n['type'] == 'refund' ? Icons.payments_outlined : Icons.coffee_outlined, color: caramel),
                          title: Text('${n['title']}', style: TextStyle(fontWeight: n['is_read'] == true ? FontWeight.w400 : FontWeight.w700)),
                          subtitle: Text('${n['message']}\n${dateTimeText(n['created_at'])}'),
                          isThreeLine: true,
                          onTap: orderId == null
                              ? null
                              : () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => OrderDetailScreen(id: orderId))),
                        );
                      },
                    ),
            ),
    );
  }
}
