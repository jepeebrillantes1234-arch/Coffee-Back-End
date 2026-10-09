import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/api.dart';
import '../core/role_home.dart';
import '../core/theme.dart';

/// Set to true if you want to show Google/Apple buttons.
/// These are not working yet, so they are turned off for now.
const bool kShowSocialLogin = false;

class AuthScreen extends StatefulWidget {
  const AuthScreen({super.key, this.initialRegister = false});

  final bool initialRegister;

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();

  bool _register = false;
  bool _hide = true;
  bool _remember = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _register = widget.initialRegister;
    SharedPreferences.getInstance().then((p) {
      if (mounted) setState(() => _remember = p.getBool('remember_me') ?? true);
    });
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      if (_register) {
        await Api.I.register(
          _name.text.trim(),
          _email.text.trim(),
          _phone.text.trim(),
          _password.text,
        );
      } else {
        await Api.I.login(_email.text.trim(), _password.text);
      }
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('remember_me', _remember);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => homeForRole(Api.I.role)),
      );
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _forgot() {
    showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Forgot password?'),
        content: const Text(
          'Automatic password reset is not available yet. Please contact KJM Coffee for assistance.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  void _soon(String what) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text('$what login coming soon.')));
  }

  OutlineInputBorder _border(Color c, [double w = 1]) => OutlineInputBorder(
    borderRadius: BorderRadius.circular(16),
    borderSide: BorderSide(color: c, width: w),
  );

  InputDecoration _dec(String hint, IconData icon, {Widget? suffix}) =>
      InputDecoration(
        hintText: hint,
        hintStyle: const TextStyle(color: Color(0xFF9A8F8A)),
        prefixIcon: Icon(icon, color: espresso),
        suffixIcon: suffix,
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(vertical: 18),
        border: _border(const Color(0xFFEDE5DE)),
        enabledBorder: _border(const Color(0xFFEDE5DE)),
        focusedBorder: _border(espresso, 1.5),
        errorBorder: _border(Colors.red.shade300),
        focusedErrorBorder: _border(Colors.red.shade400, 1.5),
      );

  String? _required(String? v, String msg) =>
      (v == null || v.trim().isEmpty) ? msg : null;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFFBF7F2),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 24, 24, 24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // ---------- LOGO ----------
                    Center(
                      child: Image.asset(
                        'assets/images/logo1.png',
                        height: 104,
                        cacheHeight: 320,
                        errorBuilder: (_, e, s) => const Icon(
                          Icons.local_cafe,
                          size: 72,
                          color: espresso,
                        ),
                      ),
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'KJM COFFEE',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 3,
                        color: espresso,
                      ),
                    ),
                    const SizedBox(height: 2),
                    const Text(
                      'COFFEE • JOY • MOMENTS',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 11,
                        letterSpacing: 3,
                        color: Color(0xFF8A7F7A),
                      ),
                    ),
                    const SizedBox(height: 30),

                    // ---------- TITLE ----------
                    Text(
                      _register ? 'Create Account' : 'Welcome Back!',
                      style: const TextStyle(
                        fontSize: 28,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF1E1412),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _register
                          ? 'Sign up to start ordering your coffee.'
                          : 'Log in to continue your coffee journey.',
                      style: const TextStyle(color: Color(0xFF6F625E)),
                    ),
                    const SizedBox(height: 22),

                    // ---------- FIELDS ----------
                    if (_register) ...[
                      TextFormField(
                        controller: _name,
                        textInputAction: TextInputAction.next,
                        textCapitalization: TextCapitalization.words,
                        decoration: _dec('Full name', Icons.person_outline),
                        validator: (v) =>
                            _required(v, 'Please enter your name.'),
                      ),
                      const SizedBox(height: 14),
                    ],
                    TextFormField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      decoration: _dec(
                        _register ? 'Email' : 'Email or Phone',
                        Icons.mail_outline,
                      ),
                      validator: (v) => _required(
                        v,
                        _register
                            ? 'Please enter your email.'
                            : 'Please enter your email or phone.',
                      ),
                    ),
                    const SizedBox(height: 14),
                    if (_register) ...[
                      TextFormField(
                        controller: _phone,
                        keyboardType: TextInputType.phone,
                        textInputAction: TextInputAction.next,
                        decoration: _dec(
                          'Phone (optional)',
                          Icons.phone_outlined,
                        ),
                      ),
                      const SizedBox(height: 14),
                    ],
                    TextFormField(
                      controller: _password,
                      obscureText: _hide,
                      textInputAction: TextInputAction.done,
                      onFieldSubmitted: (_) => _busy ? null : _submit(),
                      decoration: _dec(
                        _register
                            ? 'Password (at least 8 characters)'
                            : 'Password',
                        Icons.lock_outline,
                        suffix: IconButton(
                          icon: Icon(
                            _hide
                                ? Icons.visibility_outlined
                                : Icons.visibility_off_outlined,
                            color: espresso,
                          ),
                          onPressed: () => setState(() => _hide = !_hide),
                        ),
                      ),
                      validator: (v) {
                        if (v == null || v.isEmpty)
                          return 'Please enter your password.';
                        if (_register && v.length < 8)
                          return 'Must be at least 8 characters.';
                        return null;
                      },
                    ),

                    // ---------- REMEMBER ME / FORGOT ----------
                    if (!_register)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Row(
                          children: [
                            SizedBox(
                              width: 28,
                              height: 28,
                              child: Checkbox(
                                value: _remember,
                                activeColor: espresso,
                                visualDensity: VisualDensity.compact,
                                onChanged: (v) =>
                                    setState(() => _remember = v ?? true),
                              ),
                            ),
                            const SizedBox(width: 6),
                            const Text(
                              'Remember me',
                              style: TextStyle(
                                fontSize: 13,
                                color: Color(0xFF4A3F3B),
                              ),
                            ),
                            const Spacer(),
                            TextButton(
                              onPressed: _forgot,
                              style: TextButton.styleFrom(
                                padding: EdgeInsets.zero,
                                minimumSize: const Size(0, 32),
                                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                              ),
                              child: const Text(
                                'Forgot password?',
                                style: TextStyle(
                                  fontSize: 13,
                                  color: Color(0xFF4A3F3B),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),

                    if (_error != null) ...[
                      const SizedBox(height: 12),
                      Text(_error!, style: const TextStyle(color: Colors.red)),
                    ],
                    const SizedBox(height: 20),

                    // ---------- BUTTON ----------
                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      style: FilledButton.styleFrom(
                        minimumSize: const Size.fromHeight(56),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      child: _busy
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : Text(
                              _register ? 'Sign Up' : 'Log In',
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                    ),

                    // ---------- GOOGLE / APPLE (turned off) ----------
                    if (kShowSocialLogin) ...[
                      const SizedBox(height: 20),
                      Row(
                        children: const [
                          Expanded(child: Divider()),
                          Padding(
                            padding: EdgeInsets.symmetric(horizontal: 12),
                            child: Text(
                              'or',
                              style: TextStyle(color: Colors.black54),
                            ),
                          ),
                          Expanded(child: Divider()),
                        ],
                      ),
                      const SizedBox(height: 16),
                      _socialButton(
                        label: 'Continue with Google',
                        icon: const Text(
                          'G',
                          style: TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF4285F4),
                          ),
                        ),
                        onTap: () => _soon('Google'),
                      ),
                      const SizedBox(height: 10),
                      _socialButton(
                        label: 'Continue with Apple',
                        icon: const Icon(
                          Icons.apple,
                          size: 26,
                          color: Colors.black,
                        ),
                        onTap: () => _soon('Apple'),
                      ),
                    ],

                    // ---------- SIGN UP / LOG IN ----------
                    const SizedBox(height: 22),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          _register
                              ? 'Already have an account?'
                              : "Don't have an account?",
                          style: const TextStyle(color: Color(0xFF4A3F3B)),
                        ),
                        TextButton(
                          onPressed: () => setState(() {
                            _register = !_register;
                            _error = null;
                            _formKey.currentState?.reset();
                          }),
                          child: Text(
                            _register ? 'Log in' : 'Sign up',
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              color: espresso,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _socialButton({
    required String label,
    required Widget icon,
    required VoidCallback onTap,
  }) {
    return OutlinedButton(
      onPressed: onTap,
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(54),
        backgroundColor: Colors.white,
        side: const BorderSide(color: Color(0xFFEDE5DE)),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          SizedBox(width: 28, child: Center(child: icon)),
          const SizedBox(width: 12),
          Text(
            label,
            style: const TextStyle(
              color: Color(0xFF2B1A17),
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}


