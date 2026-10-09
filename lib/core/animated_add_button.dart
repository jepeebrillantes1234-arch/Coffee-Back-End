import 'package:flutter/material.dart';

import 'theme.dart';

class AnimatedAddButton extends StatefulWidget {
  const AnimatedAddButton({super.key, required this.onPressed, this.size = 30});

  final VoidCallback onPressed;
  final double size;

  @override
  State<AnimatedAddButton> createState() => _AnimatedAddButtonState();
}

class _AnimatedAddButtonState extends State<AnimatedAddButton> {
  bool _animating = false;

  Future<void> _tap() async {
    if (_animating) return;
    setState(() => _animating = true);
    await Future<void>.delayed(const Duration(milliseconds: 150));
    if (!mounted) return;
    setState(() => _animating = false);
    widget.onPressed();
  }

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Customize and add to cart',
      child: InkWell(
        onTap: _tap,
        customBorder: const CircleBorder(),
        child: AnimatedScale(
          scale: _animating ? 1.16 : 1,
          duration: const Duration(milliseconds: 140),
          curve: Curves.easeOutBack,
          child: AnimatedRotation(
            turns: _animating ? 0.125 : 0,
            duration: const Duration(milliseconds: 140),
            child: Container(
              width: widget.size,
              height: widget.size,
              decoration: const BoxDecoration(
                color: espresso,
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 100),
                child: Icon(
                  _animating ? Icons.check : Icons.add,
                  key: ValueKey(_animating),
                  size: widget.size * 0.66,
                  color: Colors.white,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
