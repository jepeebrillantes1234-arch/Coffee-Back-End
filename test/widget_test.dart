import 'package:flutter_test/flutter_test.dart';

import 'package:coffee_customer/main.dart';

void main() {
  testWidgets('shows the KJM customer and rider login screen', (tester) async {
    await tester.pumpWidget(const KjmApp());
    await tester.pumpAndSettle();

    expect(find.text('Welcome sa KJM Coffee Brew'), findsOneWidget);
    expect(find.text('Mag-login'), findsOneWidget);
  });
}
