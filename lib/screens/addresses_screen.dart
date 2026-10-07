import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/util.dart';

class AddressesScreen extends StatefulWidget {
  const AddressesScreen({super.key});

  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final a = await Api.I.addresses();
      if (mounted) setState(() => _items = a);
    } on ApiException catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _delete(int id) async {
    try {
      await Api.I.deleteAddress(id);
      await _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mga Saved Address')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _items.isEmpty
              ? const Center(child: Padding(padding: EdgeInsets.all(24), child: Text('Wala pang saved address.\nMakakapag-save ka habang nagche-checkout.', textAlign: TextAlign.center)))
              : ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: _items.length,
                  itemBuilder: (_, i) {
                    final a = _items[i];
                    return Card(
                      child: ListTile(
                        leading: const Icon(Icons.place_outlined),
                        title: Text('${a['label'] ?? 'Home'}'),
                        subtitle: Text('${a['full_address']}\n${dbl(a['latitude']).toStringAsFixed(5)}, ${dbl(a['longitude']).toStringAsFixed(5)}'),
                        isThreeLine: true,
                        trailing: IconButton(icon: const Icon(Icons.delete_outline, color: Colors.red), onPressed: () => _delete(a['id'] as int)),
                      ),
                    );
                  },
                ),
    );
  }
}
