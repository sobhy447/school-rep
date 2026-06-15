using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Tenancy.Domain.Tenants;

public sealed class SubscriptionStatus : Enumeration<SubscriptionStatus>
{
    public static readonly SubscriptionStatus Trial = new(1, "TRIAL");
    public static readonly SubscriptionStatus Active = new(2, "ACTIVE");
    public static readonly SubscriptionStatus Suspended = new(3, "SUSPENDED");
    public static readonly SubscriptionStatus Cancelled = new(4, "CANCELLED");
    public static readonly SubscriptionStatus Archived = new(5, "ARCHIVED");

    private SubscriptionStatus(int id, string name)
        : base(id, name)
    {
    }
}
