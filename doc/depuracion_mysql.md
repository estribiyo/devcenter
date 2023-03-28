# Depuración de MySQL con tshark

```
sudo tshark -i lo -Y "mysql.command==3" -T fields -e mysql.query > consultas.sql
```
