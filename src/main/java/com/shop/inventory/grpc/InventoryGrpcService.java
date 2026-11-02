package com.shop.inventory.grpc;

import com.shop.inventory.repository.StockRepository;
import inventory.v1.InventoryServiceGrpc;
import inventory.v1.InventoryOuterClass;
import io.grpc.stub.StreamObserver;
import net.devh.boot.grpc.server.service.GrpcService;
import org.springframework.beans.factory.annotation.Autowired;

@GrpcService
public class InventoryGrpcService extends InventoryServiceGrpc.InventoryServiceImplBase {

    @Autowired
    private StockRepository stockRepository;

    @Override
    public void getStock(InventoryOuterClass.GetStockRequest request, 
                         StreamObserver<InventoryOuterClass.GetStockResponse> responseObserver) {
        
        long productId = Long.parseLong(request.getProductId());
        
        // البحث عن الكمية المتاحة في جدول stock
        int availableQuantity = stockRepository.findById(productId)
                .map(Stock::getAvailable)
                .orElse(0);

        // بناء الرد وإرساله لعميل لارافل
        InventoryOuterClass.GetStockResponse response = InventoryOuterClass.GetStockResponse.newBuilder()
                .setProductId(String.valueOf(productId))
                .setQuantity(availableQuantity)
                .build();

        responseObserver.onNext(response);
        responseObserver.onCompleted();
    }
}