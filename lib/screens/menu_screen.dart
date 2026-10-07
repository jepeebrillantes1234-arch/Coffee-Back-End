import 'dart:async';

import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import '../state/favorites.dart';
import '../state/nav.dart';
import 'favorites_screen.dart';
import 'notifications_screen.dart';
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
  int _unread = 0;
  Timer? _bell;

  @override
  void initState() {
    super.initState();
    _init();
    Favorites.I.load();
    _loadUnread();
    _bell = Timer.periodic(const Duration(seconds: 20), (_) => _loadUnread());
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
    _bell?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _loadUnread() async {
    try {
      final n = await Api.I.notifications();
      if (mounted) setState(() => _unread = (n['unread'] as num?)?.toInt() ?? 0);
    } catch (_) {}
  }

  Future<void> _init() async {
    try {
      final c = await Api.I.categories();
      if (mounted) setState(() => _cats = c);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
    await _loadProducts();
  }

  Future<void> _loadProducts() async {
    if (mounted) setState(() => _loading = true);
    try {
      final p = await Api.I.products(categoryId: _cat, search: _search.text.trim());
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
      appBar: AppBar(
        title: const Text('Menu'),
        actions: [
          IconButton(
            tooltip: 'Favorites',
            icon: const Icon(Icons.favorite_border),
            onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const FavoritesScreen())),
          ),
          IconButton(
            tooltip: 'Notifications',
            icon: Badge(isLabelVisible: _unread > 0, label: Text('$_unread'), child: const Icon(Icons.notifications_none)),
            onPressed: () async {
              await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const NotificationsScreen()));
              _loadUnread();
            },
          ),
        ],
      ),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: TextField(
            controller: _search,
            textInputAction: TextInputAction.search,
            onSubmitted: (_) => _loadProducts(),
            decoration: InputDecoration(
              hintText: 'Hanapin ang kape...',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: IconButton(
                icon: const Icon(Icons.clear),
                onPressed: () {
                  _search.clear();
                  _loadProducts();
                },
              ),
              filled: true,
              fillColor: Colors.white,
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
            ),
          ),
        ),
        SizedBox(
          height: 52,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            children: [
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: const Text('Lahat'),
                  selected: _cat == null,
                  onSelected: (_) {
                    setState(() => _cat = null);
                    _loadProducts();
                  },
                ),
              ),
              for (final c in _cats)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text('${c['name']}'),
                    selected: _cat == c['id'],
                    onSelected: (_) {
                      setState(() => _cat = c['id'] as int);
                      _loadProducts();
                    },
                  ),
                ),
            ],
          ),
        ),
        if (_error != null) Padding(padding: const EdgeInsets.all(12), child: Text(_error!, style: const TextStyle(color: Colors.red))),
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _loadProducts,
                  child: _products.isEmpty
                      ? ListView(children: const [SizedBox(height: 120), Center(child: Text('Walang nahanap na product.'))])
                      : GridView.builder(
                          padding: const EdgeInsets.all(12),
                          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 2, childAspectRatio: 0.78, crossAxisSpacing: 12, mainAxisSpacing: 12),
                          itemCount: _products.length,
                          itemBuilder: (_, i) => productCard(context, _products[i]),
                        ),
                ),
        ),
      ]),
    );
  }
}

/// Ginagamit din sa Favorites screen
Widget productCard(BuildContext context, Map<String, dynamic> p) {
  return InkWell(
    borderRadius: BorderRadius.circular(16),
    onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ProductScreen(id: p['id'] as int))),
    child: Card(
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Expanded(
          child: Stack(fit: StackFit.expand, children: [
            NetImage(p['image_url']?.toString()),
            Positioned(
              top: 4,
              right: 4,
              child: ListenableBuilder(
                listenable: Favorites.I,
                builder: (_, __) {
                  final fav = Favorites.I.has(p['id'] as int);
                  return Material(
                    color: Colors.white.withValues(alpha: 0.85),
                    shape: const CircleBorder(),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: () => Favorites.I.toggle(p),
                      child: Padding(
                        padding: const EdgeInsets.all(6),
                        child: Icon(fav ? Icons.favorite : Icons.favorite_border, size: 20, color: fav ? Colors.red : espresso),
                      ),
                    ),
                  );
                },
              ),
            ),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.all(10),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${p['name']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 2),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text(peso(dbl(p['base_price'])), style: const TextStyle(color: caramel, fontWeight: FontWeight.w700)),
              if (dbl(p['avg_rating']) > 0)
                Text('★ ${dbl(p['avg_rating']).toStringAsFixed(1)}', style: const TextStyle(fontSize: 12, color: Colors.amber)),
            ]),
          ]),
        ),
      ]),
    ),
  );
}
