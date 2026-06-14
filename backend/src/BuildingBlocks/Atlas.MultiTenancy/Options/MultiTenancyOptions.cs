namespace Atlas.MultiTenancy.Options;

public sealed class MultiTenancyOptions
{
    public const string SectionName = "MultiTenancy";

    public string TenantClaimType { get; set; } = "tenant_id";

    public string TenantHeaderName { get; set; } = "X-Tenant-Id";

    public bool AllowHeaderResolution { get; set; }

    public IList<string> BypassPaths { get; set; } = new List<string>();
}
