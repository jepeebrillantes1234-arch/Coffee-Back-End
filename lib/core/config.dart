class AppConfig {
  static const String apiBase = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://banana-fetal-eloquence.ngrok-free.dev/api/v1',
  );

  static const String googleServerClientId = 
      '776313233164-512fb8183e640nn7b19gffa44i0m34ui.apps.googleusercontent.com';
}