import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/theme.dart';
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

// Para makita pa rin ang rootTab / menuRequest ng mga file na nag-import ng root_screen.dart
export '../state/nav.dart';

class RootScreen extends StatelessWidget {
  const RootScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<int>(
      valueListenable: rootTab,
      builder: (_, tab, __) => Scaffold(
        body: IndexedStack(index: tab, children: const [
          HomeScreen(),
          MenuScreen(),
          CartScreen(),
          OrdersScreen(),
          _AccountScreen(),
        ]),
        bottomNavigationBar: _BottomBar(index: tab, onTap: (i) => rootTab.value = i),
      ),
    );
  }
}

class _BottomBar extends StatelessWidget {
  final int index;
  final ValueChanged<int> onTap;
  const _BottomBar({required this.index, required this.onTap});

  static const _items = [
    (Icons.home_outlined, 'HOME'),
    (Icons.grid_view_rounded, 'MENU'),
    (Icons.shopping_cart_outlined, 'CART'),
    (Icons.assignment_outlined, 'ORDER'),
    (Icons.person_outline, 'PROFILE'),
  ];

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: Cart.I,
      builder: (_, __) => Container(
        color: const Color(0xFF140C0A),
        child: SafeArea(
          top: false,
          child: SizedBox(
            height: 64,
            child: Row(children: [
              for (var i = 0; i < _items.length; i++)
                Expanded(
                  child: InkWell(
                    onTap: () => onTap(i),
                    child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Badge(
                        isLabelVisible: i == 2 && Cart.I.count > 0,
                        label: Text('${Cart.I.count}'),
                        child: Icon(_items[i].$1, size: 26, color: i == index ? caramel : Colors.white),
                      ),
                      const SizedBox(height: 3),
                      Text(_items[i].$2,
                          style: TextStyle(fontSize: 10, letterSpacing: 0.6, color: i == index ? caramel : Colors.white)),
                    ]),
                  ),
                ),
            ]),
          ),
        ),
      ),
    );
  }
}

class _AccountScreen extends StatefulWidget {
  const _AccountScreen();

  @override
  State<_AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<_AccountScreen> {
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
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            TextField(controller: name, decoration: const InputDecoration(labelText: 'Pangalan')),
            TextField(controller: phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Phone')),
            TextField(controller: pass, obscureText: true, decoration: const InputDecoration(labelText: 'Bagong password (opsyonal)', helperText: 'Hindi bababa sa 8 character')),
          ]),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Kanselahin')),
          FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(100, 44)), onPressed: () => Navigator.pop(ctx, true), child: const Text('I-save')),
        ],
      ),
    );
    if (ok != true) return;

    try {
      await Api.I.updateProfile(name: name.text.trim(), phone: phone.text.trim(), password: pass.text);
      if (mounted) {
        setState(() {});
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Na-update ang profile')));
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _deleteAccount() async {
    final password = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Burahin ang account?'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('Mabubura ang iyong account at mawawala ang access dito.'),
          const SizedBox(height: 12),
          TextField(controller: password, obscureText: true, decoration: const InputDecoration(labelText: 'Ilagay ang password para kumpirmahin')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Kanselahin')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Burahin')),
        ],
      ),
    );
    if (confirmed != true) { password.dispose(); return; }
    try {
      await Api.I.deleteAccount(password.text);
      Cart.I.clear();
      Favorites.I.clear();
      if (mounted) Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(builder: (_) => const AuthScreen()), (_) => false);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
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
      Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(builder: (_) => const AuthScreen()), (_) => false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final u = Api.I.user ?? {};
    void open(Widget w) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => w));

    return Scaffold(
      appBar: AppBar(title: const Text('Account')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Card(
          child: ListTile(
            leading: const CircleAvatar(child: Icon(Icons.person)),
            title: Text('${u['name'] ?? ''}'),
            subtitle: Text('${u['email'] ?? ''}'),
            trailing: const Icon(Icons.edit_outlined),
            onTap: _editProfile,
          ),
        ),
        Card(
          child: Column(children: [
            ListTile(leading: const Icon(Icons.place_outlined), title: const Text('Mga Saved Address'), trailing: const Icon(Icons.chevron_right), onTap: () => open(const AddressesScreen())),
            const Divider(height: 1),
            ListTile(leading: const Icon(Icons.favorite_border), title: const Text('Mga Paborito'), trailing: const Icon(Icons.chevron_right), onTap: () => open(const FavoritesScreen())),
            const Divider(height: 1),
            ListTile(leading: const Icon(Icons.notifications_none), title: const Text('Mga Notification'), trailing: const Icon(Icons.chevron_right), onTap: () => open(const NotificationsScreen())),
          ]),
        ),
        const SizedBox(height: 12),
        ListTile(leading: const Icon(Icons.delete_forever_outlined, color: Colors.red), title: const Text('Burahin ang account', style: TextStyle(color: Colors.red)), onTap: _deleteAccount),
        FilledButton(onPressed: _logout, child: const Text('Logout')),
      ]),
    );
  }
}

