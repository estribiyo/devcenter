# MySQL

## mysqldiff

A falta de una utilidad mejor, se instaló esta -que deja bastante que desear, pero por lo menos hace el grueso del trabajo en una comparación entre dos esquemas MySQL-.

```
mysqldiff --force --difftype=sql --server1=user:password@$host dborigen:dbdestino
```

## Depuración de MySQL con tshark

```
sudo tshark -i lo -Y "mysql.command==3" -T fields -e mysql.query > consultas.sql
```
