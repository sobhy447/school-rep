using System.Linq.Expressions;
using Atlas.Persistence.PostgreSql.Abstractions;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Persistence.PostgreSql.Query;

public static class GlobalQueryFilters
{
    public static void ApplySoftDeleteFilter(ModelBuilder modelBuilder)
    {
        foreach (var entityType in modelBuilder.Model.GetEntityTypes())
        {
            if (entityType.IsOwned() || entityType.FindPrimaryKey() is null)
            {
                continue;
            }

            if (!typeof(ISoftDeletableEntity).IsAssignableFrom(entityType.ClrType))
            {
                continue;
            }

            ParameterExpression parameter = Expression.Parameter(entityType.ClrType, "e");

            MethodCallExpression isDeleted = Expression.Call(
                typeof(EF),
                nameof(EF.Property),
                [typeof(bool)],
                parameter,
                Expression.Constant(nameof(ISoftDeletableEntity.IsDeleted)));

            LambdaExpression filter = Expression.Lambda(Expression.Not(isDeleted), parameter);

            modelBuilder.Entity(entityType.ClrType).HasQueryFilter(filter);
        }
    }
}
