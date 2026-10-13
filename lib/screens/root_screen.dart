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
      builder: (_, tab, _) => PopScope<Object?>(
        canPop: false,
        onPopInvokedWithResult: (didPop, _) {
          if (!didPop && rootTab.value != 0) rootTab.value = 0;
        },
        child: Scaffold(
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
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        border: Border(top: BorderSide(color: Theme.of(context).dividerColor)),
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
                                    ? Theme.of(context).colorScheme.primary
                                    : Theme.of(context).colorScheme.onSurfaceVariant,
                              ),
                            ),
                          )
                        else
                          Icon(
                            item.$2,
                            size: 23,
                            color: item.$1 == index
                                ? Theme.of(context).colorScheme.primary
                                : Theme.of(context).colorScheme.onSurfaceVariant,
                          ),
                        const SizedBox(height: 3),
                        Text(
                          item.$3,
                          style: TextStyle(
                            fontSize: 10,
                            color: item.$1 == index
                                ? Theme.of(context).colorScheme.primary
                                : Theme.of(context).colorScheme.onSurfaceVariant,
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
      backgroundColor: Theme.of(context).colorScheme.surface,
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
    return InkWell(
      onTap: onTap,
      child: SizedBox(
        height: 56,
        width: double.infinity,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Row(
            children: [
              Icon(icon, color: color ?? espresso),
              const SizedBox(width: 32),
              Expanded(
                child: Text(
                  label,
                  maxLines: 1,
                  softWrap: false,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: color ?? espresso,
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              if (badgeCount != null && badgeCount > 0) ...[
                const SizedBox(width: 12),
                Container(
                  width: 30,
                  height: 30,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(
                    color: Color(0xFFE86B68),
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    badgeCount.toString(),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ],
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
          const SnackBar(content: Text('Profile photo updated.')),
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
              'Could not open the gallery or upload the photo.',
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
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Edit profile'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: name,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(labelText: 'Full name'),
              ),
              TextField(
                controller: phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(labelText: 'Phone number'),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(minimumSize: const Size(100, 44)),
            onPressed: () {
              if (name.text.trim().isEmpty) return;
              Navigator.pop(ctx, true);
            },
            child: const Text('Save'),
          ),
        ],
      ),
    );
    if (ok == true) {
      try {
        await Api.I.updateProfile(
          name: name.text.trim(),
          phone: phone.text.trim(),
        );
        if (mounted) {
          setState(() {});
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(const SnackBar(content: Text('Profile updated.')));
        }
      } on ApiException catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context)
              .showSnackBar(SnackBar(content: Text(e.message)));
        }
      }
    }
    name.dispose();
    phone.dispose();
  }

  Future<void> _changePassword() async {
    final current = TextEditingController();
    final next = TextEditingController();
    final confirm = TextEditingController();
    final formKey = GlobalKey<FormState>();
    final accepted = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Change password'),
        content: Form(
          key: formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextFormField(
                  controller: current,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: 'Current password'),
                  validator: (value) => (value == null || value.isEmpty)
                      ? 'Enter your current password'
                      : null,
                ),
                TextFormField(
                  controller: next,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'New password',
                    helperText: 'Use at least 8 characters',
                  ),
                  validator: (value) => (value == null || value.length < 8)
                      ? 'Password must be at least 8 characters'
                      : null,
                ),
                TextFormField(
                  controller: confirm,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Confirm new password',
                  ),
                  validator: (value) => value != next.text
                      ? 'Passwords do not match'
                      : null,
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              if (formKey.currentState?.validate() ?? false) {
                Navigator.pop(ctx, true);
              }
            },
            child: const Text('Update password'),
          ),
        ],
      ),
    );
    if (accepted == true) {
      try {
        await Api.I.changePassword(
          currentPassword: current.text,
          password: next.text,
          passwordConfirmation: confirm.text,
        );
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Password updated successfully.')),
          );
        }
      } on ApiException catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context)
              .showSnackBar(SnackBar(content: Text(e.message)));
        }
      }
    }
    current.dispose();
    next.dispose();
    confirm.dispose();
  }

  Future<void> _deleteAccount() async {
    final password = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete account?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Your account will be deleted and you will lose access to it.',
            ),
            const SizedBox(height: 12),
            TextField(
              controller: password,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'Enter your password to confirm',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Delete'),
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
        child: StatefulBuilder(
          builder: (context, sheetSetState) => Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.edit_outlined),
                title: const Text('Edit profile'),
                subtitle: const Text('Change your full name or phone number'),
                onTap: () => Navigator.pop(context, 'edit'),
              ),
              ListTile(
                leading: const Icon(Icons.lock_outline),
                title: const Text('Change password'),
                onTap: () => Navigator.pop(context, 'password'),
              ),
              SwitchListTile.adaptive(
                secondary: Icon(AppThemeController.I.mode == ThemeMode.dark
                    ? Icons.dark_mode_outlined
                    : Icons.light_mode_outlined),
                title: const Text('Dark mode'),
                value: AppThemeController.I.mode == ThemeMode.dark,
                onChanged: (enabled) async {
                  await AppThemeController.I.setMode(
                    enabled ? ThemeMode.dark : ThemeMode.light,
                  );
                  sheetSetState(() {});
                },
              ),
              ListTile(
                leading: const Icon(Icons.delete_outline, color: Colors.red),
                title: const Text(
                  'Delete account',
                  style: TextStyle(color: Colors.red),
                ),
                onTap: () => Navigator.pop(context, 'delete'),
              ),
              ListTile(
                leading: const Icon(Icons.logout_rounded),
                title: const Text('Log out'),
                onTap: () => Navigator.pop(context, 'logout'),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
    if (!mounted) return;
    if (action == 'edit') await _editProfile();
    if (action == 'password') await _changePassword();
    if (action == 'delete') await _deleteAccount();
    if (action == 'logout') await _logout();
  }

  void _openPaymentInfo() {
    showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Payment methods'),
        content: const Text(
          'Choose an available payment method at checkout. Depending on the order, you can pay with Cash on Delivery or GCash.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('OK'),
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
          'For help with an order, open Orders and select it to view its status and rider details.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close'),
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

  Widget _profileRow(BuildContext context, IconData icon, String title, VoidCallback onTap) =>
      ListTile(
        leading: Icon(icon, color: Theme.of(context).colorScheme.primary, size: 21),
        title: Text(
          title,
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
        ),
        trailing: Icon(
          Icons.chevron_right_rounded,
          size: 20,
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16),
        visualDensity: const VisualDensity(vertical: -1),
        onTap: onTap,
      );

  Widget _profileMenuCard(BuildContext context, List<Widget> children) => Card(
    elevation: 0,
    color: Theme.of(context).cardColor,
    shape: RoundedRectangleBorder(
      side: BorderSide(color: Theme.of(context).dividerColor),
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
                      const SizedBox(height: 4),
                      Text(
                        'Customer account',
                        style: TextStyle(
                          fontSize: 13,
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Edit profile',
                  onPressed: _editProfile,
                  icon: const Icon(Icons.edit_outlined, size: 20),
                ),
              ],
            ),
          ),
          _profileMenuCard(context, [
            _profileRow(
              context,
              Icons.receipt_long_outlined,
              'My Orders',
              () => rootTab.value = 3,
            ),
            const Divider(height: 1, indent: 52),
            _profileRow(
              context,
              Icons.location_on_outlined,
              'Addresses',
              () => open(const AddressesScreen()),
            ),
            const Divider(height: 1, indent: 52),
            _profileRow(
              context,
              Icons.credit_card_outlined,
              'Payment Methods',
              _openPaymentInfo,
            ),
            const Divider(height: 1, indent: 52),
            _profileRow(
              context,
              Icons.favorite_border_rounded,
              'Favorites',
              () => open(const FavoritesScreen()),
            ),
            const Divider(height: 1, indent: 52),
            _profileRow(
              context,
              Icons.notifications_none_rounded,
              'Notifications',
              () => open(const NotificationsScreen()),
            ),
            const Divider(height: 1, indent: 52),
            _profileRow(
              context,
              Icons.help_outline_rounded,
              'Help & Support',
              _openHelp,
            ),
            const Divider(height: 1, indent: 52),
            _profileRow(context, Icons.info_outline_rounded, 'About', _openAbout),
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
