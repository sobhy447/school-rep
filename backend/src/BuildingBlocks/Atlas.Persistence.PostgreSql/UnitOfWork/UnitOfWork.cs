using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Persistence.PostgreSql.Context;

namespace Atlas.Persistence.PostgreSql.UnitOfWork;

public sealed class UnitOfWork<TContext>(TContext context) : IUnitOfWork
    where TContext : AtlasDbContextBase
{
    public Task<int> SaveChangesAsync(CancellationToken cancellationToken = default) =>
        context.SaveChangesAsync(cancellationToken);
}
