import 'package:flutter/material.dart';

/// Gumagalaw na banner na may "loading bar" (parang stories).
/// - Awtomatikong lilipat bawat [interval]
/// - Puwedeng i-swipe; titigil habang hawak ng daliri
/// - Kahit ilan ang slides
class PromoCarousel extends StatefulWidget {
  final List<Widget> slides;
  final Duration interval;
  const PromoCarousel({
    super.key,
    required this.slides,
    this.interval = const Duration(seconds: 5),
  });

  @override
  State<PromoCarousel> createState() => _PromoCarouselState();
}

class _PromoCarouselState extends State<PromoCarousel>
    with SingleTickerProviderStateMixin {
  final PageController _page = PageController();
  late final AnimationController _ctrl = AnimationController(
    vsync: this,
    duration: widget.interval,
  );
  int _index = 0;

  int get _n => widget.slides.length;

  @override
  void initState() {
    super.initState();
    _ctrl.addStatusListener((s) {
      if (s == AnimationStatus.completed) _next();
    });
    if (_n > 1) _ctrl.forward();
  }

  @override
  void didUpdateWidget(covariant PromoCarousel old) {
    super.didUpdateWidget(old);
    if (old.slides.length != _n) {
      _index = 0;
      if (_page.hasClients) _page.jumpToPage(0);
      _ctrl.stop();
      if (_n > 1) {
        _ctrl.forward(from: 0);
      } else {
        _ctrl.value = 0;
      }
    }
  }

  @override
  void dispose() {
    _ctrl.dispose();
    _page.dispose();
    super.dispose();
  }

  void _next() {
    if (_n <= 1 || !_page.hasClients) return;
    _page.animateToPage(
      (_index + 1) % _n,
      duration: const Duration(milliseconds: 380),
      curve: Curves.easeInOut,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Expanded(
          child: Listener(
            onPointerDown: (_) => _ctrl.stop(),
            onPointerUp: (_) {
              if (_n > 1) _ctrl.forward();
            },
            onPointerCancel: (_) {
              if (_n > 1) _ctrl.forward();
            },
            child: PageView(
              controller: _page,
              onPageChanged: (i) {
                setState(() => _index = i);
                if (_n > 1) _ctrl.forward(from: 0);
              },
              children: widget.slides,
            ),
          ),
        ),
        if (_n > 1) ...[
          const SizedBox(height: 8),
          AnimatedBuilder(
            animation: _ctrl,
            builder: (_, _) => Row(
              children: [
                for (var i = 0; i < _n; i++)
                  Expanded(
                    child: Container(
                      height: 4,
                      margin: const EdgeInsets.symmetric(horizontal: 2),
                      decoration: BoxDecoration(
                        color: const Color(0x55877A70),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: FractionallySizedBox(
                        alignment: Alignment.centerLeft,
                        widthFactor: i < _index
                            ? 1
                            : (i == _index ? _ctrl.value : 0),
                        child: Container(
                          decoration: BoxDecoration(
                            color: const Color(0xFF4B2E25),
                            borderRadius: BorderRadius.circular(4),
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}
