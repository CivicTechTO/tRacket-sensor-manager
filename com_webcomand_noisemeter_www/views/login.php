        <div class="login">
            <h2>Login</h2>
            <form method="post">
<?php if(isset($message) && $message): ?>
                <div class="message">
<?= $message ?>
                </div>
<?php endif; ?>
                <p>Login to manage your tRacket sensors.</p>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" placeholder="name@example.com" value="<?= htmlspecialchars($email ?? '') ?>" />
                </div>
                <div class="field checkbox">
                    <input id="remember" name="remember" type="checkbox" checked /><label class="help" for="remember" title="Check to remain logged in from this web browser until you explicitly logout."> Remember Me</label>
                </div>
                <input type="submit" value="Login" />
            </form>
        </div>
