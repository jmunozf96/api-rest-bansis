SELECT nombre, stock FROM SGI_Inv_Productos where stock > 0 and nombre like '%funda%'


select * from SGI_Inv_Grupos where Codigo in (select distinct Padre from SGI_Inv_Grupos)

select * from SGI_Inv_Bodegas

update SGI_Inv_Productos set stock=1000 where codigo = '4040010008'