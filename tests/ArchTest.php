<?php

// The same presets the apps run, so the package never fails an app's architecture tests.
arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel();
