import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/animated_add_button.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import '../state/favorites.dart';
import '../state/nav.dart';
import 'product_screen.dart';

class MenuScreen extends StatefulWidget {
  const MenuScreen({super.key});

  @override
  State<MenuScreen> createState() => _MenuScreenState();
}

class _MenuScreenState extends State<MenuScreen> {
  List<Map<String, dynamic>> _cats = [];
  List<Map<String, dynamic>> _products = [];
  int? _cat;
  final _search = TextEditingController();
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _init();
    Favorites.I.load();
    menuRequest.addListener(_onRequest);
  }

  // Galing sa Home: pumili ng category o nag-search
  void _onRequest() {
    final r = menuRequest.value;
    if (r == null) return;
    menuRequest.value = null;
    setState(() => _cat = r['category'] as int?);
    _search.text = (r['search'] ?? '').toString();
    _loadProducts();
  }

  @override
  void dispose() {
    menuRequest.removeListener(_onRequest);
    _search.dispose();
    super.dispose();
  }

  Future<void> _init() async {
    try {
      final c = await Api.I.categories();
      if (mounted) {
        setState(() {
          _cats = c;
          if (_cat == null && c.isNotEmpty)
            _cat = (c.first['id'] as num).toInt();
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
    await _loadProducts();
  }

  Future<void> _loadProducts() async {
    if (mounted) setState(() => _loading = true);
    try {
      final p = await Api.I.products(
        categoryId: _cat,
        search: _search.text.trim(),
      );
      if (!mounted) return;
      setState(() {
        _products = p;
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
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: espresso,
        centerTitle: true,
        automaticallyImplyLeading: false,
        title: const Text(
          'Menu',
          style: TextStyle(
            color: Colors.black,
            fontSize: 17,
            fontWeight: FontWeight.w700,
          ),
        ),
        leading: IconButton(
          tooltip: 'Back to home',
          onPressed: () => rootTab.value = 0,
          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            size: 20,
            color: Color(0xFF242424),
          ),
        ),
      ),
      body: Column(
        children: [
          SizedBox(
            height: 56,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 7),
              children: [
                for (final category in _cats)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text('${category['name']}'),
                      selected: _cat == category['id'],
                      onSelected: (_) {
                        setState(() => _cat = (category['id'] as num).toInt());
                        _loadProducts();
                      },
                      showCheckmark: false,
                      labelStyle: TextStyle(
                        color: _cat == category['id']
                            ? Colors.white
                            : const Color(0xFF222222),
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                      selectedColor: espresso,
                      backgroundColor: const Color(0xFFF6F5F3),
                      shape: const StadiumBorder(),
                      side: BorderSide.none,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 11,
                        vertical: 4,
                      ),
                    ),
                  ),
              ],
            ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
              child: Text(_error!, style: const TextStyle(color: Colors.red)),
            ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : RefreshIndicator(
                    onRefresh: _loadProducts,
                    child: _products.isEmpty
                        ? ListView(
                            children: const [
                              SizedBox(height: 120),
                              Center(child: Text('Walang nahanap na product.')),
                            ],
                          )
                        : ListView.separated(
                            padding: const EdgeInsets.fromLTRB(16, 6, 16, 20),
                            itemCount: _products.length,
                            separatorBuilder: (_, _) => const Divider(
                              height: 1,
                              indent: 84,
                              color: Color(0xFFF0EFED),
                            ),
                            itemBuilder: (_, index) =>
                                _menuRow(_products[index]),
                          ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _menuRow(Map<String, dynamic> product) {
    final description = (product['description'] ?? '').toString().trim();
    return InkWell(
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ProductScreen(id: (product['id'] as num).toInt()),
        ),
      ),
      child: SizedBox(
        height: 92,
        child: Row(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: SizedBox(
                width: 68,
                height: 68,
                child: NetImage(product['image_url']?.toString()),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${product['name']}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 13,
                      color: Color(0xFF202020),
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 3),
                  if (description.isNotEmpty)
                    Text(
                      description,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 11,
                        color: Color(0xFF888888),
                      ),
                    ),
                  const SizedBox(height: 5),
                  Text(
                    peso(dbl(product['base_price'])),
                    style: const TextStyle(
                      fontSize: 12,
                      color: Colors.black,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            AnimatedAddButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) =>
                      ProductScreen(id: (product['id'] as num).toInt()),
                ),
              ),
              size: 29,
            ),
          ],
        ),
      ),
    );
  }
}

/// Ginagamit din sa Favorites screen
Widget productCard(BuildContext context, Map<String, dynamic> p) {
  return InkWell(
    borderRadius: BorderRadius.circular(16),
    onTap: () => Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ProductScreen(id: p['id'] as int)),
    ),
    child: Card(
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Stack(
              fit: StackFit.expand,
              children: [
                NetImage(p['image_url']?.toString()),
                Positioned(
                  top: 4,
                  right: 4,
                  child: ListenableBuilder(
                    listenable: Favorites.I,
                    builder: (_, _) {
                      final fav = Favorites.I.has(p['id'] as int);
                      return Material(
                        color: Colors.white.withValues(alpha: 0.85),
                        shape: const CircleBorder(),
                        child: InkWell(
                          customBorder: const CircleBorder(),
                          onTap: () => Favorites.I.toggle(p),
                          child: Padding(
                            padding: const EdgeInsets.all(6),
                            child: Icon(
                              fav ? Icons.favorite : Icons.favorite_border,
                              size: 20,
                              color: fav ? Colors.red : espresso,
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${p['name']}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 2),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      peso(dbl(p['base_price'])),
                      style: const TextStyle(
                        color: caramel,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    if (dbl(p['avg_rating']) > 0)
                      Text(
                        '★ ${dbl(p['avg_rating']).toStringAsFixed(1)}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: Colors.amber,
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
