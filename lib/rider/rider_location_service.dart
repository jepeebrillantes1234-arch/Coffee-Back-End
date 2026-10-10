import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';

import '../core/api.dart';

/// Nagpapadala ng lokasyon ng rider sa server tuwing 5 segundo habang may aktibong delivery.
class LocationService {
  LocationService._();
  static final LocationService I = LocationService._();

  StreamSubscription<Position>? _sub;
  Timer? _timer;
  Position? _last;
  int? _deliveryId;

  final ValueNotifier<Position?> position = ValueNotifier<Position?>(null);
  final ValueNotifier<bool> tracking = ValueNotifier<bool>(false);
  final ValueNotifier<String?> error = ValueNotifier<String?>(null);

  Future<bool> _ensurePermission() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      error.value = 'Turn on your phone location/GPS.';
      return false;
    }
    var perm = await Geolocator.checkPermission();
    if (perm == LocationPermission.denied) {
      perm = await Geolocator.requestPermission();
    }
    if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) {
      error.value = 'Location permission is required. Allow it in Settings.';
      return false;
    }
    return true;
  }

  LocationSettings _settings() {
    if (defaultTargetPlatform == TargetPlatform.android) {
      return AndroidSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 5,
        intervalDuration: const Duration(seconds: 5),
        foregroundNotificationConfig: const ForegroundNotificationConfig(
          notificationTitle: 'Coffee Rider',
          notificationText: 'Sharing location during delivery',
          enableWakeLock: true,
        ),
      );
    }
    if (defaultTargetPlatform == TargetPlatform.iOS) {
      return AppleSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 5,
        activityType: ActivityType.automotiveNavigation,
        pauseLocationUpdatesAutomatically: false,
        showBackgroundLocationIndicator: true,
        allowBackgroundLocationUpdates: true,
      );
    }
    return const LocationSettings(accuracy: LocationAccuracy.high, distanceFilter: 5);
  }

  Future<void> start({required int deliveryId}) async {
    _deliveryId = deliveryId;
    if (_sub != null) return;
    if (!await _ensurePermission()) return;

    _sub = Geolocator.getPositionStream(locationSettings: _settings()).listen(
      (p) {
        _last = p;
        position.value = p;
      },
      onError: (Object e) => error.value = e.toString(),
    );
    tracking.value = true;
    error.value = null;

    try {
      final p = await Geolocator.getCurrentPosition();
      _last = p;
      position.value = p;
    } catch (_) {}

    _timer = Timer.periodic(const Duration(seconds: 5), (_) => _send());
    await _send();
  }

  Future<void> _send() async {
    final p = _last;
    if (p == null) return;
    try {
      final kmh = p.speed < 0 ? 0.0 : p.speed * 3.6;
      final id = _deliveryId;
      if (id == null) return;
      await Api.I.sendLocation(id, p.latitude, p.longitude, kmh);
      error.value = null;
    } on ApiException catch (e) {
      error.value = e.message;
    } catch (_) {}
  }

  Future<void> stop() async {
    await _sub?.cancel();
    _sub = null;
    _timer?.cancel();
    _timer = null;
    _last = null;
    _deliveryId = null;
    position.value = null;
    tracking.value = false;
  }
}



