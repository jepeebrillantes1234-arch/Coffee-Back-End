import 'dart:async';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../core/api.dart';
import '../core/theme.dart';
import '../core/widgets.dart';
import '../state/cart.dart';
import '../state/favorites.dart';
import '../state/nav.dart';
import 'addresses_screen.dart';
import 'auth_screen.dart';
import 'cart_screen.dart';
import 'favorites_screen.dart';
import 'home_screen.dart';
import 'menu_screen.dart';
import 'notifications_screen.dart';
import 'orders_screen.dart';
import 'promos_screen.dart';

// Para makita pa rin ang rootTab / menuRequest ng mga file na nag-import ng root_screen.dart
export '../state/nav.dart';

class RootScreen extends StatelessWidget {
  const RootScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<int>(
      valueListenable: rootTab,
      builder: (_, tab, _) => Scaffold(
        key: rootScaffoldKey,
        drawer: const _CustomerDrawer(),
        body: IndexedStack(
          index: tab,
          children: const [
            HomeScreen(),
            MenuScreen(),
            CartScreen(),
            OrdersScreen(),
            _AccountScreen(),
          ],
        ),
        bottomNavigationBar: _BottomBar(
          index: tab,
          onTap: (i) => rootTab.value = i,
        ),
      ),
    );
  }
}

class _BottomBar extends StatelessWidget {
  final int index;
  final ValueChanged<int> onTap;
  const _BottomBar({required this.index, required this.onTap});

  static const _items = [
    (0, Icons.home_outlined, 'Home'),
    (1, Icons.grid_view_rounded, 'Menu'),
    (2, Icons.shopping_cart_outlined, 'Cart'),
    (3, Icons.assignment_outlined, 'Orders'),
    (4, Icons.person_outline, 'Profile'),
  ];

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Color(0xFFFFFCF7),
        border: Border(top: BorderSide(color: Color(0xFFE9E1D8))),
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 60,
          child: Row(
            children: [
              for (final item in _items)
                Expanded(
                  child: InkWell(
                    onTap: () => onTap(item.$1),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        if (item.$1 == 2)
                          AnimatedBuilder(
                            animation: Cart.I,
                            builder: (context, _) => Badge(
                              isLabelVisible: Cart.I.count > 0,
                              label: Text('${Cart.I.count}'),
                              backgroundColor: const Color(0xFFE53935),
                              textColor: Colors.white,
                              child: Icon(
                                item.$2,
                                size: 23,
                                color: item.$1 == index
                                    ? espresso
                                    : const Color(0xFF837A73),
                              ),
                            ),
                          )
                        else
                          Icon(
                            item.$2,
                            size: 23,
                            color: item.$1 == index
                                ? espresso
                                : const Color(0xFF837A73),
                          ),
                        const SizedBox(height: 3),
                        Text(
                          item.$3,
                          style: TextStyle(
                            fontSize: 10,
                            color: item.$1 == index
                                ? espresso
                                : const Color(0xFF837A73),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CustomerDrawer extends StatefulWidget {
  const _CustomerDrawer();

  @override
  State<_CustomerDrawer> createState() => _CustomerDrawerState();
}

class _CustomerDrawerState extends State<_CustomerDrawer> {
  int _orderCount = 0;
  int _cartCount = 0;
  Timer? _orderCountTimer;

  @override
  void initState() {
    super.initState();
    _cartCount = Cart.I.count;
    Cart.I.addListener(_onCartChanged);
    _loadOrderCount();
    _orderCountTimer = Timer.periodic(
      const Duration(seconds: 20),
      (_) => _loadOrderCount(),
    );
  }

  void _onCartChanged() {
    if (mounted && _cartCount != Cart.I.count) {
      setState(() => _cartCount = Cart.I.count);
    }
  }

  @override
  void dispose() {
    Cart.I.removeListener(_onCartChanged);
    _orderCountTimer?.cancel();
    super.dispose();
  }

  Future<void> _loadOrderCount() async {
    try {
      final orders = await Api.I.orders();
      if (mounted && _orderCount != orders.length) {
        setState(() => _orderCount = orders.length);
      }
    } catch (_) {
      // Keep the last count when the API is temporarily unavailable.
    }
  }

  void _tab(BuildContext context, int tab) {
    Navigator.pop(context);
    rootTab.value = tab;
  }

  void _page(BuildContext context, Widget page) {
    Navigator.pop(context);
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => page));
  }

  Future<void> _logout(BuildContext context) async {
    final navigator = Navigator.of(context);
    Navigator.pop(context);
    Cart.I.clear();
    Favorites.I.clear();
    await Api.I.logout();
    rootTab.value = 0;
    if (navigator.mounted) {
      navigator.pushAndRemoveUntil(
        MaterialPageRoute<void>(builder: (_) => const AuthScreen()),
        (_) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = Api.I.user ?? {};
    return Drawer(
      backgroundColor: const Color(0xFFFFFCF7),
      child: SafeArea(
        child: Column(
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(18, 24, 16, 22),
              decoration: const BoxDecoration(color: Color(0xFFF7F4F0)),
              child: InkWell(
                borderRadius: BorderRadius.circular(16),
                onTap: () => _tab(context, 4),
                child: Row(
                  children: [
                    SizedBox(
                      width: 58,
                      height: 58,
                      child: ClipOval(
                        child: (user['avatar_url'] ?? '').toString().isEmpty
                            ? const ColoredBox(
                                color: Color(0xFFE8DED4),
                                child: Icon(
                                  Icons.person_rounded,
                                  color: espresso,
                                  size: 34,
                                ),
                              )
                            : Image.network(
                                fixUrl(user['avatar_url'].toString()),
                                fit: BoxFit.cover,
                                headers: const {
                                  'ngrok-skip-browser-warning': 'true',
                                },
                                errorBuilder: (_, __, ___) => const ColoredBox(
                                  color: Color(0xFFE8DED4),
                                  child: Icon(
                                    Icons.person_rounded,
                                    color: espresso,
                                    size: 34,
                                  ),
                                ),
                              ),
                      ),
                    ),
                    const SizedBox(width: 13),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Customer Profile',
                            style: TextStyle(
                              fontSize: 11,
                              color: Color(0xFF74675E),
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            '${user['name'] ?? 'Customer'}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                              color: espresso,
                            ),
                          ),
                          if ('${user['email'] ?? ''}'.isNotEmpty)
                            Text(
                              '${user['email']}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xFF74675E),
                              ),
                            ),
                          if ('${user['phone'] ?? ''}'.isNotEmpty)
                            Text(
                              '${user['phone']}',
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xFF74675E),
                              ),
                            ),
                        ],
                      ),
                    ),
                    const Icon(
                      Icons.chevron_right_rounded,
                      color: Color(0xFF74675E),
                    ),
                  ],
                ),
              ),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(vertical: 8),
                children: [
                  _item(
                    context,
                    Icons.home_outlined,
                    'Home',
                    () => _tab(context, 0),
                  ),
                  _item(
                    context,
                    Icons.grid_view_rounded,
                    'Menu',
                    () => _tab(context, 1),
                  ),
                  _item(
                    context,
                    Icons.shopping_cart_outlined,
                    'Cart',
                    () => _tab(context, 2),
                    badgeCount: _cartCount,
                  ),
                  _item(
                    context,
                    Icons.assignment_outlined,
                    'My Orders',
                    () => _tab(context, 3),
                    badgeCount: _orderCount,
                  ),
                  _item(
                    context,
                    Icons.local_offer_outlined,
                    'Promos',
                    () => _page(context, const PromosScreen()),
                  ),
                  const Divider(indent: 18, endIndent: 18),
                  _item(
                    context,
                    Icons.place_outlined,
                    'Saved Addresses',
                    () => _page(context, const AddressesScreen()),
                  ),
                  _item(
                    context,
                    Icons.favorite_border,
                    'Favorites',
                    () => _page(context, const FavoritesScreen()),
                  ),
                  _item(
                    context,
                    Icons.notifications_none,
                    'Notifications',
                    () => _page(context, const NotificationsScreen()),
                  ),
                  _item(
                    context,
                    Icons.person_outline,
                    'Profile',
                    () => _tab(context, 4),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            _item(
              context,
              Icons.logout,
              'Log out',
              () => _logout(context),
              color: const Color(0xFF8D3C32),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }

  Widget _item(
    BuildContext context,
    IconData icon,
    String label,
    VoidCallback onTap, {
    Color? color,
    int? badgeCount,
  }) {
    return ListTile(
      leading: Icon(icon, color: color ?? espresso),
      trailing: badgeCount != null && badgeCount > 0
          ? Container(
              constraints: const BoxConstraints(minWidth: 22, minHeight: 22),
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
              decoration: const BoxDecoration(
                color: Color(0xFFE86B68),
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: Text(
                badgeCount.toString(),
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            )
          : null,
      title: Text(
        label,
        style: TextStyle(
          color: color ?? espresso,
          fontSize: 14,
          fontWeight: FontWeight.w600,
        ),
      ),
      onTap: onTap,
      dense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 20),
    );
  }
}

class _AccountScreen extends StatefulWidget {
  const _AccountScreen();

  @override
  State<_AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<_AccountScreen> {
  final _imagePicker = ImagePicker();
  bool _avatarBusy = false;

  Future<void> _changeAvatar() async {
    try {
      final image = await _imagePicker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 85,
        maxWidth: 1200,
      );
      if (image == null || !mounted) return;
      setState(() => _avatarBusy = true);
      await Api.I.uploadAvatar(image.path);
      if (mounted) {
        setState(() {});
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Na-update ang profile photo')),
        );
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Hindi mabuksan ang gallery o ma-upload ang larawan.',
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _avatarBusy = false);
    }
  }

  Widget _avatarButton({double size = 68}) {
    final url = (Api.I.user?['avatar_url'] ?? '').toString();
    return InkWell(
      onTap: _avatarBusy ? null : _changeAvatar,
      customBorder: const CircleBorder(),
      child: SizedBox(
        width: size,
        height: size,
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Positioned.fill(
              child: ClipOval(
                child: url.isEmpty
                    ? ColoredBox(
                        color: const Color(0xFFF3E9DC),
                        child: Icon(
                          Icons.person,
                          color: espresso,
                          size: size * .55,
                        ),
                      )
                    : NetImage(url, width: size, height: size),
              ),
            ),
            Positioned(
              right: 0,
              bottom: 0,
              child: Container(
                width: 24,
                height: 24,
                decoration: const BoxDecoration(
                  color: espresso,
                  shape: BoxShape.circle,
                ),
                alignment: Alignment.center,
                child: _avatarBusy
                    ? const SizedBox(
                        width: 13,
                        height: 13,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(
                        Icons.camera_alt_rounded,
                        size: 13,
                        color: Colors.white,
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _editProfile() async {
    final u = Api.I.user ?? {};
    final name = TextEditingController(text: '${u['name'] ?? ''}');
    final phone = TextEditingController(text: '${u['phone'] ?? ''}');
    final pass = TextEditingController();

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('I-edit ang profile'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: name,
                decoration: const InputDecoration(labelText: 'Pangalan'),
              ),
              TextField(
                controller: phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(labelText: 'Phone'),
              ),
              TextField(
                controller: pass,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Bagong password (opsyonal)',
                  helperText: 'Hindi bababa sa 8 character',
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Kanselahin'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(minimumSize: const Size(100, 44)),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('I-save'),
          ),
        ],
      ),
    );
    if (ok != true) return;

    try {
      await Api.I.updateProfile(
        name: name.text.trim(),
        phone: phone.text.trim(),
        password: pass.text,
      );
      if (mounted) {
        setState(() {});
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Na-update ang profile')));
      }
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _deleteAccount() async {
    final password = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Burahin ang account?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Mabubura ang iyong account at mawawala ang access dito.',
            ),
            const SizedBox(height: 12),
            TextField(
              controller: password,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'Ilagay ang password para kumpirmahin',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Kanselahin'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Burahin'),
          ),
        ],
      ),
    );
    if (confirmed != true) {
      password.dispose();
      return;
    }
    try {
      await Api.I.deleteAccount(password.text);
      Cart.I.clear();
      Favorites.I.clear();
      if (mounted)
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const AuthScreen()),
          (_) => false,
        );
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      password.dispose();
    }
  }

  Future<void> _logout() async {
    Cart.I.clear();
    Favorites.I.clear();
    await Api.I.logout();
    rootTab.value = 0;
    if (mounted) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const AuthScreen()),
        (_) => false,
      );
    }
  }

  Future<void> _openSettings() async {
    final action = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.edit_outlined),
              title: const Text('I-edit ang profile'),
              onTap: () => Navigator.pop(context, 'edit'),
            ),
            ListTile(
              leading: const Icon(Icons.delete_outline, color: Colors.red),
              title: const Text(
                'Burahin ang account',
                style: TextStyle(color: Colors.red),
              ),
              onTap: () => Navigator.pop(context, 'delete'),
            ),
            ListTile(
              leading: const Icon(Icons.logout_rounded),
              title: const Text('Mag-log out'),
              onTap: () => Navigator.pop(context, 'logout'),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (!mounted) return;
    if (action == 'edit') await _editProfile();
    if (action == 'delete') await _deleteAccount();
    if (action == 'logout') await _logout();
  }

  void _openPaymentInfo() {
    showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Payment methods'),
        content: const Text(
          'Piliin ang available na paraan ng bayad sa checkout. Depende sa order, maaaring Cash on Delivery o GCash ang pagpipilian.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Sige'),
          ),
        ],
      ),
    );
  }

  void _openHelp() {
    showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Help & Support'),
        content: const Text(
          'Para sa tulong tungkol sa order, buksan ang Orders at piliin ang order para makita ang status at rider details.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Isara'),
          ),
        ],
      ),
    );
  }

  void _openAbout() {
    showAboutDialog(
      context: context,
      applicationName: 'KJM Coffee Shop',
      applicationIcon: const Icon(
        Icons.coffee_rounded,
        color: espresso,
        size: 36,
      ),
      applicationLegalese: 'Customer app',
    );
  }

  Widget _profileRow(IconData icon, String title, VoidCallback onTap) =>
      ListTile(
        leading: Icon(icon, color: espresso, size: 21),
        title: Text(
          title,
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
        ),
        trailing: const Icon(
          Icons.chevron_right_rounded,
          size: 20,
          color: Colors.black45,
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16),
        visualDensity: const VisualDensity(vertical: -1),
        onTap: onTap,
      );

  Widget _profileMenuCard(List<Widget> children) => Card(
    elevation: 0,
    color: Colors.white,
    shape: RoundedRectangleBorder(
      side: const BorderSide(color: Color(0xFFECE8E4)),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Column(children: children),
  );

  @override
  Widget build(BuildContext context) {
    final u = Api.I.user ?? {};
    void open(Widget page) =>
        Navigator.of(context).push(MaterialPageRoute(builder: (_) => page));

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text(
          'Profile',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
        ),
        actions: [
          IconButton(
            tooltip: 'Settings',
            onPressed: _openSettings,
            icon: const Icon(Icons.settings_outlined),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 28),
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(4, 8, 4, 22),
            child: Row(
              children: [
                _avatarButton(size: 76),
                const SizedBox(width: 15),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${u['name'] ?? 'Customer'}',
                        style: const TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if ('${u['phone'] ?? ''}'.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          '${u['phone']}',
                          style: const TextStyle(
                            fontSize: 13,
                            color: Colors.black54,
                          ),
                        ),
                      ],
                      if ('${u['email'] ?? ''}'.isNotEmpty) ...[
                        const SizedBox(height: 3),
                        Text(
                          '${u['email']}',
                          style: const TextStyle(
                            fontSize: 12,
                            color: Colors.black54,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'I-edit ang profile',
                  onPressed: _editProfile,
                  icon: const Icon(Icons.edit_outlined, size: 20),
                ),
              ],
            ),
          ),
          _profileMenuCard([
            _profileRow(
              Icons.receipt_long_outlined,
              'My Orders',
              () => rootTab.value = 3,
            ),
            const Divider(height: 1, indent: 52),
            _profileRow(
              Icons.location_on_outlined,
              'Addresses',
              () => open(const AddressesScreen()),
            ),
            const Divider(height: 1, indent: 52),
            _profileRow(
              Icons.credit_card_outlined,
              'Payment Methods',
              _openPaymentInfo,
            ),
            const Divider(height: 1, indent: 52),
            _profileRow(
              Icons.favorite_border_rounded,
              'Favorites',
              () => open(const FavoritesScreen()),
            ),
            const Divider(height: 1, indent: 52),
            _profileRow(
              Icons.notifications_none_rounded,
              'Notifications',
              () => open(const NotificationsScreen()),
            ),
            const Divider(height: 1, indent: 52),
            _profileRow(
              Icons.help_outline_rounded,
              'Help & Support',
              _openHelp,
            ),
            const Divider(height: 1, indent: 52),
            _profileRow(Icons.info_outline_rounded, 'About', _openAbout),
          ]),
          const SizedBox(height: 22),
          SizedBox(
            height: 50,
            child: FilledButton(
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFFE86B68),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(13),
                ),
              ),
              onPressed: _logout,
              child: const Text(
                'Log Out',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
