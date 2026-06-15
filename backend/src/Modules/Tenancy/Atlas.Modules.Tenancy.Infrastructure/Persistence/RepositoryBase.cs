using Atlas.BuildingBlocks.Domain.Repositories;
using Atlas.Persistence.PostgreSql.Context;
using Atlas.Persistence.PostgreSql.Query;
using Atlas.SharedKernel.Primitives;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Modules.Tenancy.Infrastructure.Persistence;

internal abstract class RepositoryBase<TAggregate>(TenancyDbContext context) : IRepository<TAggregate, long>
    where TAggregate : AggregateRoot<long>
{
    protected TenancyDbContext Context { get; } = context;

    protected DbSet<TAggregate> Set => Context.Set<TAggregate>();

    public async Task<TAggregate?> GetByIdAsync(long id, CancellationToken cancellationToken = default) =>
        await Set.FirstOrDefaultAsync(entity => entity.Id == id, cancellationToken);

    public async Task<TAggregate?> FirstOrDefaultAsync(
        ISpecification<TAggregate> specification,
        CancellationToken cancellationToken = default) =>
        await SpecificationEvaluator.GetQuery(Set, specification).FirstOrDefaultAsync(cancellationToken);

    public async Task<IReadOnlyList<TAggregate>> ListAsync(
        ISpecification<TAggregate> specification,
        CancellationToken cancellationToken = default) =>
        await SpecificationEvaluator.GetQuery(Set, specification).ToListAsync(cancellationToken);

    public async Task<bool> AnyAsync(
        ISpecification<TAggregate> specification,
        CancellationToken cancellationToken = default) =>
        await SpecificationEvaluator.GetQuery(Set, specification).AnyAsync(cancellationToken);

    public async Task<int> CountAsync(
        ISpecification<TAggregate> specification,
        CancellationToken cancellationToken = default) =>
        await SpecificationEvaluator.GetQuery(Set, specification).CountAsync(cancellationToken);

    public async Task AddAsync(TAggregate aggregate, CancellationToken cancellationToken = default) =>
        await Set.AddAsync(aggregate, cancellationToken);

    public void Update(TAggregate aggregate) => Set.Update(aggregate);

    public void Remove(TAggregate aggregate) => Set.Remove(aggregate);
}
