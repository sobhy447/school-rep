using System.Text;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata;

namespace Atlas.Persistence.PostgreSql.Conventions;

public static class SnakeCaseNamingConvention
{
    public static void ApplySnakeCaseNames(this ModelBuilder modelBuilder)
    {
        foreach (IMutableEntityType entityType in modelBuilder.Model.GetEntityTypes())
        {
            string? tableName = entityType.GetTableName();
            if (tableName is not null)
            {
                entityType.SetTableName(ToSnakeCase(tableName));
            }

            StoreObjectIdentifier? storeObject =
                StoreObjectIdentifier.Create(entityType, StoreObjectType.Table);

            foreach (IMutableProperty property in entityType.GetProperties())
            {
                string columnName = storeObject is { } store
                    ? property.GetColumnName(store) ?? property.Name
                    : property.Name;

                property.SetColumnName(ToSnakeCase(columnName));
            }

            foreach (IMutableKey key in entityType.GetKeys())
            {
                string? name = key.GetName();
                if (name is not null)
                {
                    key.SetName(ToSnakeCase(name));
                }
            }

            foreach (IMutableForeignKey foreignKey in entityType.GetForeignKeys())
            {
                string? name = foreignKey.GetConstraintName();
                if (name is not null)
                {
                    foreignKey.SetConstraintName(ToSnakeCase(name));
                }
            }

            foreach (IMutableIndex index in entityType.GetIndexes())
            {
                string? name = index.GetDatabaseName();
                if (name is not null)
                {
                    index.SetDatabaseName(ToSnakeCase(name));
                }
            }
        }
    }

    private static string ToSnakeCase(string input)
    {
        if (string.IsNullOrEmpty(input))
        {
            return input;
        }

        var builder = new StringBuilder(input.Length + 8);

        for (int i = 0; i < input.Length; i++)
        {
            char current = input[i];

            if (char.IsUpper(current))
            {
                if (i > 0 &&
                    (!char.IsUpper(input[i - 1]) ||
                     (i + 1 < input.Length && char.IsLower(input[i + 1]))))
                {
                    builder.Append('_');
                }

                builder.Append(char.ToLowerInvariant(current));
            }
            else
            {
                builder.Append(current);
            }
        }

        return builder.ToString();
    }
}
