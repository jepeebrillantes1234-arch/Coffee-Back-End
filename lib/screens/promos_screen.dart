import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import '../state/nav.dart';
import '../state/promo_state.dart';
import 'product_screen.dart';

class PromosScreen extends StatefulWidget {
  const PromosScreen({super.key});

  @override
  State<PromosScreen> createState() => _PromosScreenState();
}

class _PromosScreenState extends State<PromosScreen> {
  List<Map<String, dynamic>> _promos = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final cfg = await Api.I.appConfig();
      if (!mounted) return;
      setState(() {
        _promos = cfg['promos'] is List ? mapList(cfg['promos']) : [];
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

  Future<void> _choosePromo(Map<String, dynamic> promo) async {
    if (promo['used'] == true) return;

    final alreadySelected = SelectedPromo.I.code == promo['code'];
    SelectedPromo.I.select(promo);
    final product = promo['product'];
    final needed = promo['type'] == 'bogo'
        ? ((promo['buy_qty'] as num?)?.toInt() ?? 1) +
              ((promo['free_qty'] as num?)?.toInt() ?? 1)
        : 0;

    if (!alreadySelected) {
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Napili ang promo!'),
          content: Text(
            '${promo['headline'] ?? promo['title'] ?? promo['code']}\n'
            'Code: ${promo['code'] ?? ''} (awtomatikong ilalagay sa checkout)\n'
            '${product is Map ? 'Para sa: ${product['name']}${needed > 0 ? ' • ilalagay ang $needed piraso' : ' • piliin ang produkto'}\n' : ''}'
            'Tingnan ang detalye ng promo bago mag-checkout.',
          ),
          actions: [
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('Magpatuloy'),
            ),
          ],
        ),
      );
    }
    if (!mounted) return;

    final navigator = Navigator.of(context);
    navigator.pop();
    if (product is Map && product['id'] is num) {
      navigator.push(
        MaterialPageRoute<void>(
          builder: (_) => ProductScreen(id: (product['id'] as num).toInt()),
        ),
      );
    } else {
      rootTab.value = 1;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        title: const Text('Promos'),
        actions: [
          IconButton(
            tooltip: 'Refresh promos',
            onPressed: _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? ListView(
                children: [
                  SizedBox(height: 240),
                  Center(child: CircularProgressIndicator()),
                ],
              )
            : _error != null
            ? ListView(
                children: [
                  const SizedBox(height: 120),
                  Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(_error!, textAlign: TextAlign.center),
                    ),
                  ),
                  Center(
                    child: TextButton.icon(
                      onPressed: _load,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Subukan ulit'),
                    ),
                  ),
                ],
              )
            : _promos.isEmpty
            ? ListView(
                children: const [
                  SizedBox(height: 130),
                  Icon(
                    Icons.local_offer_outlined,
                    size: 48,
                    color: Color(0xFF9A9189),
                  ),
                  SizedBox(height: 14),
                  Center(child: Text('Wala pang promo na ipinost ang admin.')),
                ],
              )
            : ListView.separated(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
                itemCount: _promos.length,
                separatorBuilder: (_, _) => const SizedBox(height: 14),
                itemBuilder: (_, index) => _promoCard(_promos[index]),
              ),
      ),
    );
  }

  Widget _promoCard(Map<String, dynamic> promo) {
    final imageUrl = (promo['image_url'] ?? '').toString();
    final used = promo['used'] == true;
    final code = (promo['code'] ?? '').toString();
    final title = (promo['title'] ?? '').toString().trim();
    final headline = (promo['headline'] ?? '').toString().trim();
    final description = (promo['description'] ?? '').toString().trim();

    return ListenableBuilder(
      listenable: SelectedPromo.I,
      builder: (_, _) {
        final selected = SelectedPromo.I.code == code;
        return Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.07),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                width: double.infinity,
                height: 190,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    Container(
                      color: const Color(0xFFFFFCF7),
                      alignment: Alignment.center,
                      child: imageUrl.isEmpty
                          ? const Icon(
                              Icons.local_cafe_outlined,
                              size: 48,
                              color: Color(0xFF9A9189),
                            )
                          : NetImage(imageUrl, fit: BoxFit.contain),
                    ),
                    if (used)
                      Align(
                        alignment: Alignment.topRight,
                        child: Container(
                          margin: const EdgeInsets.all(10),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 6,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0xEEFFFFFF),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: const Text(
                            'Nagamit na',
                            style: TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 13, 14, 14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (title.isNotEmpty)
                      Text(
                        title,
                        style: const TextStyle(
                          fontSize: 14,
                          color: Color(0xFF756C65),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    if (headline.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        headline,
                        style: const TextStyle(
                          fontSize: 20,
                          color: espresso,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                    if (description.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(
                        description,
                        style: const TextStyle(fontSize: 14, height: 1.35),
                      ),
                    ],
                    if (code.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF3E9DC),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          'Promo code: $code',
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            color: espresso,
                          ),
                        ),
                      ),
                    ],
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: used ? null : () => _choosePromo(promo),
                        icon: Icon(
                          selected
                              ? Icons.check_circle_outline
                              : Icons.local_offer_outlined,
                        ),
                        label: Text(
                          used
                              ? 'Nagamit na'
                              : selected
                              ? 'Napili na - Gamitin'
                              : 'Gamitin ang promo',
                        ),
                        style: FilledButton.styleFrom(
                          minimumSize: const Size.fromHeight(46),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
