import 'dart:async';

import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import '../state/cart.dart';
import '../state/favorites.dart';
import '../state/app_state.dart';
import '../state/nav.dart';
import '../state/promo_state.dart';
import 'notifications_screen.dart';
import 'product_screen.dart';
import 'promo_carousel.dart';

const _serif = 'serif';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<Map<String, dynamic>> _cats = [];
  List<Map<String, dynamic>> _products = [];
  Map<String, dynamic>? _cfg;
  bool _loading = true;
  String? _error;
  int _unread = 0;
  Timer? _bell;
  Timer? _auto;
  final _page = PageController();
  final _search = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
    Favorites.I.load();
    _loadUnread();
    _bell = Timer.periodic(const Duration(seconds: 20), (_) => _loadUnread());
    rootTab.addListener(_onTab);
  }

  @override
  void dispose() {
    rootTab.removeListener(_onTab);
    _bell?.cancel();
    _auto?.cancel();
    _page.dispose();
    _search.dispose();
    super.dispose();
  }

  Future<void> _loadUnread() async {
    try {
      final n = await Api.I.notifications();
      if (mounted) setState(() => _unread = (n['unread'] as num?)?.toInt() ?? 0);
    } catch (_) {}
  }

  Future<void> _load() async {
    try {
      final cats = await Api.I.categories();
      final prods = await Api.I.products();
      Map<String, dynamic>? cfg;
      try {
        cfg = await Api.I.appConfig();
      } catch (_) {}
      prods.sort((a, b) => dbl(b['avg_rating']).compareTo(dbl(a['avg_rating'])));
      if (!mounted) return;
      setState(() {
        _cats = cats;
        _products = prods;
        _cfg = cfg;
        AppState.online = cfg?['payment_mode'] == 'online' || (cfg?['settings'] as Map?)?['payment_method'] == 'online';
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

  Map<String, dynamic>? get _firstOrder {
    final fo = _cfg?['first_order'];
    if (fo is! Map) return null;
    final m = Map<String, dynamic>.from(fo);
    return (m['enabled'] == true && m['eligible'] == true && dbl(m['percent']) > 0) ? m : null;
  }

  Map<String, dynamic>? get _foBanner {
    if (_firstOrder == null) return null;
    final b = _cfg?['first_order_banner'];
    return b is Map ? Map<String, dynamic>.from(b) : <String, dynamic>{};
  }

  List<Map<String, dynamic>> get _promos {
    final p = _cfg?['promos'];
    return p is List ? mapList(p) : [];
  }

  List<Widget> _buildSlides() {
    if (_loading) return [_loadingSlide()];
    final slides = <Widget>[];
    final fo = _foBanner;
    if (fo != null) slides.add(_discountSlide(fo));
    for (final p in _promos) {
      slides.add(_promoSlide(p));
    }
    if (slides.isEmpty) slides.add(_brandSlide());
    return slides;
  }

  // Pag-balik sa Home: i-refresh para updated ang "Nagamit na"
  void _onTab() {
    if (rootTab.value == 0) _load();
  }

  // ---------- AKSYON ----------
  void _goMenu({int? category, String? search}) {
    menuRequest.value = {'category': category, 'search': search ?? ''};
    rootTab.value = 1;
  }

  void _openProduct(Map<String, dynamic> p) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => ProductScreen(id: p['id'] as int)));
  }

  /// Mabilis na idagdag sa cart gamit ang unang option ng mga required na group
  Future<void> _quickAdd(Map<String, dynamic> p) async {
    try {
      final d = await Api.I.product(p['id'] as int);
      var unit = dbl(d['base_price']);
      final ids = <int>[];
      final names = <String>[];
      for (final g in mapList(d['option_groups'] ?? [])) {
        if (g['is_required'] != true) continue;
        final opts = mapList(g['options'] ?? []);
        if (opts.isEmpty) continue;
        ids.add(opts.first['id'] as int);
        names.add('${opts.first['name']}');
        unit += dbl(opts.first['extra_price']);
      }
      Cart.I.add(CartLine(
        productId: d['id'] as int,
        name: '${d['name']}',
        imageUrl: d['image_url']?.toString(),
        unitPrice: unit,
        optionIds: ids,
        optionNames: names,
      ));
      if (!mounted) return;
      ScaffoldMessenger.of(context).hideCurrentSnackBar();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('${d['name']} naidagdag sa cart${names.isEmpty ? '' : ' (${names.join(', ')})'}'),
        action: SnackBarAction(label: 'CART', onPressed: () => rootTab.value = 2),
      ));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  // ---------- UI ----------
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(padding: EdgeInsets.zero, children: [
          _header(),
          _categories(),
          _recommended(),
          const SizedBox(height: 24),
        ]),
      ),
    );
  }

  Widget _header() {
    final name = ('${Api.I.user?['name'] ?? 'Customer'}').trim().split(' ').first;
    final slides = _buildSlides();

    return SizedBox(
      height: 430,
      child: Stack(children: [
        Positioned.fill(child: Image.asset('assets/images/coffee.png', fit: BoxFit.cover, alignment: Alignment.topCenter)),
        Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Colors.black.withValues(alpha: 0.55), Colors.black.withValues(alpha: 0.10), Colors.black.withValues(alpha: 0.55)],
              ),
            ),
          ),
        ),
        SafeArea(
          bottom: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white, border: Border.all(color: Colors.white, width: 2)),
                  child: ClipOval(child: Image.asset('assets/images/logo1.png', fit: BoxFit.cover, cacheWidth: 160)),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('Hello, $name!',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontFamily: _serif, fontStyle: FontStyle.italic, fontSize: 24, color: Colors.white)),
                    const Text('What are you craving today?',
                        style: TextStyle(fontFamily: _serif, fontStyle: FontStyle.italic, fontSize: 12, color: Colors.white70)),
                  ]),
                ),
                IconButton(
                  icon: Badge(isLabelVisible: _unread > 0, label: Text('$_unread'), child: const Icon(Icons.notifications_none, color: Colors.white, size: 28)),
                  onPressed: () async {
                    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const NotificationsScreen()));
                    _loadUnread();
                  },
                ),
              ]),
              const SizedBox(height: 20),
              TextField(
                controller: _search,
                textInputAction: TextInputAction.search,
                onSubmitted: (v) {
                  _goMenu(search: v.trim());
                  _search.clear();
                },
                decoration: InputDecoration(
                  hintText: 'Search your favorite...',
                  prefixIcon: const Icon(Icons.search, color: espresso),
                  filled: true,
                  fillColor: Colors.white.withValues(alpha: 0.85),
                  contentPadding: const EdgeInsets.symmetric(vertical: 0),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(26), borderSide: BorderSide.none),
                ),
              ),
            ]),
          ),
        ),
        Positioned(
          left: 16,
          right: 16,
          bottom: 22,
          child: SizedBox(height: 178, child: PromoCarousel(slides: slides)),
        ),
      ]),
    );
  }

  BoxDecoration get _bannerDecoration => BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.white, width: 2.5),
        gradient: const LinearGradient(colors: [Color(0xFF9A6A3A), Color(0xFF5B3A26)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.35), blurRadius: 10, offset: const Offset(0, 4))],
      );

  Widget _loadingSlide() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 2),
      decoration: _bannerDecoration,
      child: const Center(child: SizedBox(width: 28, height: 28, child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white))),
    );
  }

  /// Karaniwang itsura ng isang slide (text sa kaliwa, larawan sa kanan)
  Widget _slideCard({
    required String title,
    required String headline,
    required String subtitle,
    required Widget? image,
    Widget? chip,
    VoidCallback? onTap,
    bool dim = false,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 2),
        decoration: _bannerDecoration,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(17),
          child: Stack(children: [
            Row(children: [
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 10, 8, 10),
                  child: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.start, children: [
                    if (title.isNotEmpty)
                      Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontFamily: _serif, fontStyle: FontStyle.italic, fontSize: 15, color: Colors.white)),
                    if (headline.isNotEmpty)
                      FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text(headline, style: const TextStyle(fontSize: 30, fontWeight: FontWeight.w900, fontStyle: FontStyle.italic, color: Colors.white, letterSpacing: 1)),
                      ),
                    if (subtitle.isNotEmpty)
                      Text(subtitle, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontFamily: _serif, fontStyle: FontStyle.italic, fontSize: 14, color: Colors.white)),
                    if (chip != null) ...[const SizedBox(height: 6), chip],
                  ]),
                ),
              ),
              if (image != null) SizedBox(width: 140, height: double.infinity, child: image),
            ]),
            if (dim)
              Positioned.fill(
                child: Container(
                  color: Colors.black.withValues(alpha: 0.6),
                  alignment: Alignment.center,
                  child: const Text('NAGAMIT MO NA', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, letterSpacing: 1.5)),
                ),
              ),
          ]),
        ),
      ),
    );
  }

  /// First Order Discount (awtomatiko). Larawan at text ay mula sa Admin > Promos.
  Widget _discountSlide(Map<String, dynamic> b) {
    final fo = _firstOrder;
    final pct = dbl(fo?['percent']);
    final pctText = pct % 1 == 0 ? pct.toInt().toString() : pct.toStringAsFixed(1);
    final max = dbl(fo?['max']);
    final img = (b['image_url'] ?? '').toString();

    String pick(String k, String fallback) {
      final v = (b[k] ?? '').toString();
      return v.isEmpty ? fallback : v;
    }

    return _slideCard(
      title: pick('title', 'First Order Discount'),
      headline: pick('headline', 'GET $pctText% OFF'),
      subtitle: (b['subtitle'] ?? 'on your order!').toString(),
      image: img.isNotEmpty ? NetImage(img) : Image.asset('assets/images/coffee1.png', fit: BoxFit.cover),
      chip: max > 0 ? Text('hanggang ${peso(max)}', style: const TextStyle(fontSize: 11, color: Colors.white70)) : null,
      onTap: () {
        _goMenu();
        ScaffoldMessenger.of(context).hideCurrentSnackBar();
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Awtomatiko ang First Order Discount sa checkout ÃƒÂ°Ã…Â¸Ã…Â½Ã¢â‚¬Â°')));
      },
    );
  }

  /// Promo mula sa Admin > Promos (kahit ilan)
  Widget _promoSlide(Map<String, dynamic> p) {
    final used = p['used'] == true;
    final img = (p['image_url'] ?? '').toString();
    final per = (p['per_user_limit'] as num?)?.toInt() ?? 1;

    return ListenableBuilder(
      listenable: SelectedPromo.I,
      builder: (_, __) {
        final selected = SelectedPromo.I.code == p['code'];
        return _slideCard(
          title: (p['title'] ?? '').toString(),
          headline: (p['headline'] ?? '').toString(),
          subtitle: (p['description'] ?? '').toString(),
          image: img.isNotEmpty ? NetImage(img) : null,
          dim: used,
          onTap: () => _claimPromo(p),
          chip: Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(
              color: selected ? success : Colors.white.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white54),
            ),
            child: Text(
              selected ? 'ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“ NAPILI Ãƒâ€šÃ‚Â· ${p['code']}' : '${p['code']} Ãƒâ€šÃ‚Â· ${per == 1 ? '1 gamit lang' : '$per gamit'}',
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Colors.white),
            ),
          ),
        );
      },
    );
  }

  /// Pinindot ang promo: awtomatikong mapupunta ang code sa checkout.
  Future<void> _claimPromo(Map<String, dynamic> p) async {
    if (p['used'] == true) {
      ScaffoldMessenger.of(context).hideCurrentSnackBar();
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Nagamit mo na ang promo na ito. 1 gamit lang bawat customer.')));
      return;
    }

    final already = SelectedPromo.I.code == p['code'];
    SelectedPromo.I.select(p);

    // Isang alert lang, at sa unang pindot lang
    if (!already) {
      final prod = p['product'];
      final need = (p['type'] == 'bogo') ? (((p['buy_qty'] as num?)?.toInt() ?? 1) + ((p['free_qty'] as num?)?.toInt() ?? 1)) : 0;
      await showDialog<void>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Napili ang promo! ÃƒÂ°Ã…Â¸Ã…Â½Ã¢â‚¬Â°'),
          content: Text(
            '${(p['headline'] ?? p['code'])}\n'
            'Code: ${p['code']} (awtomatikong ilalagay sa checkout)\n'
            '${prod is Map ? 'Para sa: ${prod['name']}${need > 0 ? ' Ãƒâ€šÃ‚Â· ilalagay ang $need piraso' : ' Ãƒâ€šÃ‚Â· 1 piraso lang'}\n' : ''}'
            '1 beses lang magagamit.',
          ),
          actions: [
            FilledButton(
              style: FilledButton.styleFrom(minimumSize: const Size(160, 44)),
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Pumili ng produkto'),
            ),
          ],
        ),
      );
    }
    if (!mounted) return;

    final prod = p['product'];
    if (prod is Map) {
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => ProductScreen(id: (prod['id'] as num).toInt())));
    } else {
      _goMenu();
    }
  }

  Widget _brandSlide() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 2),
      decoration: _bannerDecoration,
      child: Row(children: [
        Expanded(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 6, 10),
            child: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('KJM Coffee Brew', style: TextStyle(fontFamily: _serif, fontStyle: FontStyle.italic, fontSize: 15, color: Colors.white70)),
              const SizedBox(height: 4),
              const FittedBox(
                fit: BoxFit.scaleDown,
                child: Text('Kape ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ Joy ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ Moments', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, fontStyle: FontStyle.italic, color: Colors.white)),
              ),
              const SizedBox(height: 6),
              const Text('Order online, bayad sa GCash,\nhatid sa pinto mo ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂºÃ‚Âµ', style: TextStyle(fontSize: 12, color: Colors.white, height: 1.35)),
            ]),
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(right: 14),
          child: Container(
            width: 96,
            height: 96,
            decoration: const BoxDecoration(shape: BoxShape.circle, color: Colors.white),
            child: ClipOval(child: Image.asset('assets/images/logo1.png', fit: BoxFit.cover, cacheWidth: 300)),
          ),
        ),
      ]),
    );
  }

  Widget _categories() {
    String emoji(String n) {
      final s = n.toLowerCase();
      if (s.contains('non')) return 'ÃƒÂ°Ã…Â¸Ã‚Â¥Ã¢â‚¬Âº';
      if (s.contains('iced')) return 'ÃƒÂ°Ã…Â¸Ã‚Â§Ã¢â‚¬Â¹';
      if (s.contains('hot')) return 'ÃƒÂ¢Ã‹Å“Ã¢â‚¬Â¢';
      if (s.contains('pastr') || s.contains('bread')) return 'ÃƒÂ°Ã…Â¸Ã‚Â¥Ã‚Â';
      return 'ÃƒÂ°Ã…Â¸Ã‚ÂÃ‚Â½ÃƒÂ¯Ã‚Â¸Ã‚Â';
    }

    return Container(
      color: cream,
      padding: const EdgeInsets.fromLTRB(0, 14, 0, 6),
      child: Column(children: [
        Row(children: [
          const SizedBox(width: 16),
          Expanded(child: Container(height: 1.5, color: espresso.withValues(alpha: 0.5))),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 12),
            child: Text('Categories', style: TextStyle(fontFamily: _serif, fontStyle: FontStyle.italic, fontSize: 28, color: espresso)),
          ),
          Expanded(child: Container(height: 1.5, color: espresso.withValues(alpha: 0.5))),
          const SizedBox(width: 16),
        ]),
        const SizedBox(height: 12),
        if (_loading)
          const Padding(padding: EdgeInsets.all(20), child: CircularProgressIndicator())
        else
          SizedBox(
            height: 104,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: [
                for (final c in _cats)
                  InkWell(
                    onTap: () => _goMenu(category: c['id'] as int),
                    child: SizedBox(
                      width: 92,
                      child: Column(children: [
                        Container(
                          width: 64,
                          height: 64,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: Colors.white,
                            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.12), blurRadius: 6, offset: const Offset(0, 2))],
                          ),
                          child: ClipOval(
                            child: (c['image_url'] ?? '').toString().isNotEmpty
                                ? NetImage(c['image_url']?.toString(), width: 64, height: 64)
                                : Center(child: Text(emoji('${c['name']}'), style: const TextStyle(fontSize: 30))),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text('${c['name']}'.toUpperCase(),
                            textAlign: TextAlign.center,
                            maxLines: 2,
                            style: const TextStyle(fontSize: 10.5, letterSpacing: 0.6, fontWeight: FontWeight.w600, color: espresso)),
                      ]),
                    ),
                  ),
              ],
            ),
          ),
      ]),
    );
  }

  Widget _recommended() {
    final items = _products.take(6).toList();
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          const Padding(
            padding: EdgeInsets.only(left: 4),
            child: Text('Recommended For You', style: TextStyle(fontFamily: _serif, fontStyle: FontStyle.italic, fontSize: 20, color: espresso)),
          ),
          TextButton(
            onPressed: () => _goMenu(),
            child: const Text('View all', style: TextStyle(fontFamily: _serif, fontStyle: FontStyle.italic, fontSize: 15, color: espresso)),
          ),
        ]),
        if (_error != null) Padding(padding: const EdgeInsets.all(8), child: Text(_error!, style: const TextStyle(color: Colors.red))),
        if (!_loading && items.isEmpty && _error == null)
          const Padding(padding: EdgeInsets.all(24), child: Center(child: Text('Wala pang product.'))),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 3, childAspectRatio: 0.6, crossAxisSpacing: 10, mainAxisSpacing: 10),
          itemCount: items.length,
          itemBuilder: (_, i) => _miniCard(items[i]),
        ),
      ]),
    );
  }

  Widget _miniCard(Map<String, dynamic> p) {
    final rating = dbl(p['avg_rating']);
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.10), blurRadius: 6, offset: const Offset(0, 2))],
      ),
      padding: const EdgeInsets.all(7),
      child: Column(children: [
        Expanded(
          child: InkWell(
            onTap: () => _openProduct(p),
            child: Stack(children: [
              Positioned.fill(child: ClipRRect(borderRadius: BorderRadius.circular(10), child: NetImage(p['image_url']?.toString()))),
              if (rating > 0)
                Positioned(
                  top: 3,
                  left: 4,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                    decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.85), borderRadius: BorderRadius.circular(8)),
                    child: Row(mainAxisSize: MainAxisSize.min, children: [
                      const Icon(Icons.star, size: 10, color: Colors.amber),
                      Text(rating.toStringAsFixed(1), style: const TextStyle(fontSize: 9.5)),
                    ]),
                  ),
                ),
              Positioned(
                top: 0,
                right: 0,
                child: ListenableBuilder(
                  listenable: Favorites.I,
                  builder: (_, __) {
                    final fav = Favorites.I.has(p['id'] as int);
                    return GestureDetector(
                      onTap: () => Favorites.I.toggle(p),
                      child: Padding(
                        padding: const EdgeInsets.all(4),
                        child: Icon(fav ? Icons.favorite : Icons.favorite_border, size: 20, color: fav ? Colors.red : espresso),
                      ),
                    );
                  },
                ),
              ),
            ]),
          ),
        ),
        const SizedBox(height: 5),
        SizedBox(
          height: 32,
          child: Center(child: Text('${p['name']}', maxLines: 2, textAlign: TextAlign.center, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, height: 1.15))),
        ),
        Text(peso(dbl(p['base_price'])), style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: espresso)),
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          InkWell(onTap: () => _openProduct(p), child: const Padding(padding: EdgeInsets.all(2), child: Icon(Icons.shopping_cart_outlined, size: 22, color: espresso))),
          InkWell(onTap: () => _quickAdd(p), child: const Padding(padding: EdgeInsets.all(2), child: Icon(Icons.add_circle_outline, size: 26, color: espresso))),
        ]),
      ]),
    );
  }
}



