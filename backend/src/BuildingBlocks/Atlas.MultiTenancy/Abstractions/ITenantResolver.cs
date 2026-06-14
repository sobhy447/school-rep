namespace Atlas.MultiTenancy.Abstractions;

public interface ITenantResolver
{
    ValueTask<long?> ResolveAsync(CancellationToken cancellationToken = default);
}
