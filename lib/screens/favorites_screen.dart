import 'package:flutter/material.dart';

import '../state/favorites.dart';
import 'menu_screen.dart';

class FavoritesScreen extends StatefulWidget {
  const FavoritesScreen({super.key});

  @override
  State<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen> {
  @override
  void initState() {
    super.initState();
    Favorites.I.load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('❤️ Mga Paborito')),
      body: ListenableBuilder(
        listenable: Favorites.I,
        builder: (_, __) {
          final items = Favorites.I.items;
          if (items.isEmpty) {
            return const Center(child: Text('Wala ka pang paboritong kape.\nI-tap ang ♡ sa menu.', textAlign: TextAlign.center));
          }
          return GridView.builder(
            padding: const EdgeInsets.all(12),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2, childAspectRatio: 0.78, crossAxisSpacing: 12, mainAxisSpacing: 12),
            itemCount: items.length,
            itemBuilder: (_, i) => productCard(context, items[i]),
          );
        },
      ),
    );
  }
}
