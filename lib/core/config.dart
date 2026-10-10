class AppConfig {
  // Override per environment, for example with --dart-define=API_BASE_URL=https://example.ngrok-free.dev/api/v1
  static const String apiBase = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://banana-fetal-eloquence.ngrok-free.dev/api/v1',
  );

  static const String googleServerClientId = String.fromEnvironment(
    'GOOGLE_SERVER_CLIENT_ID',
  );
}
