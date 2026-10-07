import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/theme.dart';
import '../core/role_home.dart';

class AuthScreen extends StatefulWidget {
  const AuthScreen({super.key});

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  bool _register = false;
  bool _busy = false;
  String? _error;
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      if (_register) {
        await Api.I.register(_name.text.trim(), _email.text.trim(), _phone.text.trim(), _password.text);
      } else {
        await Api.I.login(_email.text.trim(), _password.text);
      }
      if (!mounted) return;
      Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => homeForRole(Api.I.role)));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  InputDecoration _dec(String l) => InputDecoration(labelText: l, border: const OutlineInputBorder(), filled: true, fillColor: Colors.white);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 400),
              child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Image.asset('assets/images/logo1.png', height: 130, cacheHeight: 400),
                const SizedBox(height: 8),
                Text(_register ? 'Gumawa ng account' : 'Welcome sa KJM Coffee Brew',
                    textAlign: TextAlign.center, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w600, color: espresso)),
                const SizedBox(height: 24),
                if (_register) ...[
                  TextField(controller: _name, decoration: _dec('Pangalan')),
                  const SizedBox(height: 12),
                ],
                TextField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: _dec(_register ? 'Email' : 'Email o phone')),
                const SizedBox(height: 12),
                if (_register) ...[
                  TextField(controller: _phone, keyboardType: TextInputType.phone, decoration: _dec('Phone (opsyonal)')),
                  const SizedBox(height: 12),
                ],
                TextField(controller: _password, obscureText: true, decoration: _dec('Password (min. 8)')),
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(_error!, style: const TextStyle(color: Colors.red)),
                ],
                const SizedBox(height: 18),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : Text(_register ? 'Mag-register' : 'Mag-login'),
                ),
                TextButton(
                  onPressed: () => setState(() {
                    _register = !_register;
                    _error = null;
                  }),
                  child: Text(_register ? 'May account na? Mag-login' : 'Wala pang account? Mag-register'),
                ),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}
