<?php

namespace App\Enums;

enum BuildPackTypes: string
{
    case NIXPACKS = 'nixpacks';
    case STATIC = 'static';
    case DOCKERFILE = 'dockerfile';
    case DOCKERCOMPOSE = 'dockercompose';
    // [COOLIFY ENHANCED: Additional build types]
    case RAILPACK = 'railpack';
    case HEROKU = 'heroku';
    case PAKETO = 'paketo';
    // [END COOLIFY ENHANCED]
}
