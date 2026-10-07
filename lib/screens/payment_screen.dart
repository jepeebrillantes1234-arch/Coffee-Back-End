import 'dart:async';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/theme.dart';
import '../core/util.dart';
import 'order_detail_screen.dart';

/// Pagbabayad sa GCash. Bubukas ang PayMongo checkout sa browser; pagbalik mo,
/// awtomatikong titingnan kung bayad na.
class PaymentScreen extends StatefulWidget {
  final Map<String, dynamic> order;
  const PaymentScreen({super.key, required this.order});

  @override
  State<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends State<PaymentScreen> with WidgetsBindingObserver {
  late Map<String, dynamic> _order;
  Timer? _poll;
  Timer? _tick;
  Duration _left = Duration.zero;
  bool _checking = false;
  bool _busy = false;
  bool _done = false;

  int get _id => _order['id'] as int;

  @override
  void initState() {
    super.initState();
    _order = widget.order;
    WidgetsBinding.instance.addObserver(this);
    _updateLeft();
    _tick = Timer.periodic(const Duration(seconds: 1), (_) => _updateLeft());
    _poll = Timer.periodic(const Duration(seconds: 4), (_) => _verify());
    // Buksan agad ang GCash checkout
    WidgetsBinding.instance.addPostFrameCallback((_) => _openCheckout());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _poll?.cancel();
    _tick?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _verify();
  }

  void _updateLeft() {
    final exp = DateTime.tryParse('${_order['payment_expires_at']}')?.toLocal();
    if (exp == null) return;
    final d = exp.difference(DateTime.now());
    if (mounted) setState(() => _left = d.isNegative ? Duration.zero : d);
  }

  void _snack(String m) {
    if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(m)));
  }

  Future<void> _openCheckout() async {
    String? url = _order['checkout_url']?.toString();
    if (url == null || url.isEmpty) {
      try {
        _order = await Api.I.payOrder(_id);
        url = _order['checkout_url']?.toString();
      } on ApiException catch (e) {
        _snack(e.message);
        return;
      }
    }
    if (url == null || url.isEmpty) {
      _snack('Walang payment link. Subukan ulit.');
      return;
    }
    final ok = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    if (!ok) _snack('Hindi mabuksan ang payment page.');
  }

  Future<void> _payAgain() async {
    setState(() => _busy = true);
    try {
      _order = await Api.I.payOrder(_id);
      if (_order['status'] != 'awaiting_payment') {
        await _verify(manual: true);
        return;
      }
      await _openCheckout();
    } on ApiException catch (e) {
      _snack(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _verify({bool manual = false}) async {
    if (_checking || _done) return;
    _checking = true;
    try {
      final o = await Api.I.verifyPayment(_id);
      if (!mounted) return;
      final st = '${o['status']}';
      if (st != 'awaiting_payment') {
        _done = true;
        _poll?.cancel();
        if (st == 'cancelled') {
          _snack('Na-cancel ang order dahil hindi nabayaran.');
          Navigator.of(context).pop();
        } else {
          _snack('Bayad na! Salamat 🎉');
          Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => OrderDetailScreen(id: _id)));
        }
      } else if (manual) {
        _snack('Wala pa kaming natatanggap na bayad. Subukan ulit pagkalipas ng ilang segundo.');
      }
    } on ApiException catch (e) {
      if (manual) _snack(e.message);
    } finally {
      _checking = false;
    }
  }

  Future<void> _cancel() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('I-cancel ang order?'),
        content: const Text('Hindi ka pa nasisingil.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Hindi')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Oo, i-cancel')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await Api.I.cancel(_id);
      _done = true;
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      _snack(e.message);
    }
  }

  String get _timeText {
    final m = _left.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = _left.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  @override
  Widget build(BuildContext context) {
    final expired = _left == Duration.zero;
    return PopScope(
      canPop: true,
      child: Scaffold(
        appBar: AppBar(title: const Text('Pagbabayad')),
        body: ListView(padding: const EdgeInsets.all(20), children: [
          const SizedBox(height: 10),
          const Text('💙', textAlign: TextAlign.center, style: TextStyle(fontSize: 56)),
          const SizedBox(height: 8),
          const Text('Magbayad gamit ang GCash', textAlign: TextAlign.center, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          Text('${_order['order_number']}', textAlign: TextAlign.center, style: const TextStyle(color: Colors.black54)),
          const SizedBox(height: 18),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(children: [
                const Text('Halagang babayaran'),
                Text(peso(dbl(_order['total'])), style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w800, color: caramel)),
                const Divider(height: 24),
                Text(expired ? 'Nag-expire na ang oras ng pagbabayad' : 'Mag-e-expire ang order sa $_timeText',
                    style: TextStyle(color: expired ? Colors.red : Colors.black54)),
              ]),
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            '1. Pindutin ang "Magbayad sa GCash".\n2. Tapusin ang bayad sa page na bubukas.\n3. Bumalik dito. Awtomatiko naming kukumpirmahin ang bayad mo.\n\nMakikita lang ng shop ang order mo kapag bayad na.',
            style: TextStyle(height: 1.5, color: Colors.black54),
          ),
          const SizedBox(height: 18),
          FilledButton(
            onPressed: _busy ? null : (expired ? null : _payAgain),
            child: _busy ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Magbayad sa GCash'),
          ),
          const SizedBox(height: 10),
          OutlinedButton(
            style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
            onPressed: () => _verify(manual: true),
            child: const Text('Nakabayad na ako'),
          ),
          const SizedBox(height: 6),
          TextButton(onPressed: _cancel, child: const Text('I-cancel ang order', style: TextStyle(color: Colors.red))),
        ]),
      ),
    );
  }
}
