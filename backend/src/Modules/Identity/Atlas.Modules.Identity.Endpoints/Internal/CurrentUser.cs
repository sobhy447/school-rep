using System.Globalization;
using System.Security.Claims;
using Microsoft.AspNetCore.Http;

namespace Atlas.Modules.Identity.Endpoints.Internal;

internal static class CurrentUser
{
    public const string UserIdClaimType = "user_id";

    public static bool TryGetUserId(HttpContext httpContext, out long userId)
    {
        userId = default;

        string? value = httpContext.User.FindFirstValue(UserIdClaimType);

        return long.TryParse(value, NumberStyles.Integer, CultureInfo.InvariantCulture, out userId) && userId > 0;
    }
}
