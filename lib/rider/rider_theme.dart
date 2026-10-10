import 'package:flutter/material.dart';

/// Kulay ng Rider mode (berde) para madaling makilala laban sa Customer mode
const riderGreen = Color(0xFF1F3D2F);

String deliveryStatusLabel(String s) {
  switch (s) {
    case 'assigned':
      return 'New assignment';
    case 'accepted':
      return 'Tinanggap';
    case 'picked_up':
      return 'Order picked up';
    case 'on_the_way':
      return 'On the way to customer';
    case 'delivered':
      return 'Delivered';
    default:
      return s;
  }
}

Color deliveryStatusColor(String s) {
  switch (s) {
    case 'assigned':
      return Colors.amber.shade800;
    case 'accepted':
      return Colors.blue.shade700;
    case 'picked_up':
      return Colors.indigo;
    case 'on_the_way':
      return Colors.purple;
    case 'delivered':
      return const Color(0xFF3C8D5A);
    default:
      return Colors.grey;
  }
}
